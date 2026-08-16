<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIdFactory;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Math;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Import\FileParser\Fit\FitDeviceType;
use App\Domain\Import\FileParser\Fit\FitManufacturer;
use App\Domain\Import\FileParser\Fit\FitProduct;
use App\Domain\Import\FileParser\Fit\FitSportType;
use App\Domain\Import\FileParser\Fit\FitStrapHeartRate;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;

final readonly class FitFileParser implements ActivityFileParser
{
    // Seconds between the Unix epoch and the FIT epoch (1989-12-31 00:00:00 UTC).
    // FIT timestamps are stored as seconds since the FIT epoch.
    private const int FIT_EPOCH_OFFSET = 631065600;
    private const float MAX_AVG_POWER_DEVIANCE = 0.1;

    public function __construct(
        private ActivityIdFactory $activityIdFactory,
        private ActivityLapsMapper $activityLapsMapper,
        private ProcessFactory $processFactory,
        private ActivityStreamsMapper $activityStreamsMapper,
        private ?SerializableTimezone $timezone,
    ) {
    }

    public function supportedExtension(): SupportedFileExtension
    {
        return SupportedFileExtension::FIT;
    }

    public function parse(RawActivityFile $file): ParsedActivityFile
    {
        $process = $this->processFactory->create(['fit-tool', (string) $file->getPath()]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotParseActivityFile(message: sprintf('fit-tool could not decode "%s": %s', $file->getPath()->getFilename(), trim($process->getErrorOutput())), activityFile: $file);
        }

        $output = $process->getOutput();

        /** @var list<array<string, mixed>> $records */
        $records = [];
        /** @var list<array<string, mixed>> $lapMessages */
        $lapMessages = [];
        /** @var list<array<string, mixed>> $hrMessages */
        $hrMessages = [];
        /** @var list<array<string, mixed>> $deviceInfoMessages */
        $deviceInfoMessages = [];
        /** @var array<string, mixed>|null $session */
        $session = null;
        /** @var array<string, mixed>|null $workout */
        $workout = null;
        $productName = null;
        $manufacturerId = null;
        $productId = null;

        $messages = Json::decodeLazy(
            json: $output,
            pointer: '/files/-/messages',
        );

        $hasMessages = false;
        foreach ($messages as $message) {
            $hasMessages = true;
            $fields = $this->fieldMap($message['fields'] ?? []);
            switch ($message['name'] ?? null) {
                case 'record':
                    $records[] = $fields;
                    break;
                case 'lap':
                    $lapMessages[] = $fields;
                    break;
                case 'hr':
                    $hrMessages[] = $fields;
                    break;
                case 'device_info':
                    $deviceInfoMessages[] = $fields;
                    break;
                case 'session':
                    $session ??= $fields;
                    break;
                case 'workout':
                    $workout ??= $fields;
                    break;
                case 'file_id':
                    $manufacturerId ??= is_numeric($fields['manufacturer'] ?? null) ? (int) round((float) $fields['manufacturer']) : null;
                    $productId ??= is_numeric($fields['product'] ?? null) ? (int) round((float) $fields['product']) : null;
                    break;
            }
            if (null === $productName && is_string($fields['product_name'] ?? null) && '' !== $fields['product_name']) {
                $productName = $fields['product_name'];
            }
        }

        if (!$hasMessages) {
            throw new CouldNotParseActivityFile(message: sprintf('No FIT messages found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $deviceName = match (true) {
            null === $manufacturerId => $productName,
            null !== $productId && FitProduct::supports($manufacturerId) => FitProduct::name($manufacturerId, $productId) ?? $productName ?? FitManufacturer::name($manufacturerId),
            default => $productName ?? FitManufacturer::name($manufacturerId),
        };

        if ([] === $records) {
            throw new CouldNotParseActivityFile(message: sprintf('No FIT "record" messages found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $session ??= [];

        $startTimestamp = (is_numeric($session['start_time'] ?? null) ? (int) round((float) $session['start_time']) : null)
            ?? (is_numeric($records[0]['timestamp'] ?? null) ? (int) round((float) $records[0]['timestamp']) : null);
        if (null === $startTimestamp) {
            throw new CouldNotParseActivityFile(message: sprintf('Could not determine start time in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $sportType = FitSportType::resolve(
            sport: $session['sport'] ?? null,
            subSport: $session['sub_sport'] ?? null,
            sportProfileName: is_string($session['sport_profile_name'] ?? null) ? trim($session['sport_profile_name']) : null
        );

        if (!$sportType instanceof SportType) {
            throw new CouldNotParseActivityFile(message: sprintf('Unsupported FIT sport %s (sub sport %s)', $session['sport'] ?? 'null', $session['sub_sport'] ?? 'null'), activityFile: $file);
        }

        $streamMap = $this->buildStreams(
            records: FitStrapHeartRate::mergeIntoRecords(
                records: $this->mergeRecordsByTimestamp($records),
                hrMessages: $hrMessages,
            ),
            startTimestamp: $startTimestamp
        );
        $activityId = $this->activityIdFactory->random();
        $work = is_numeric($session['total_work'] ?? null) ? (float) $session['total_work'] : null;
        $startDateTime = SerializableDateTime::fromTimestamp(self::FIT_EPOCH_OFFSET + $startTimestamp)->toTimezone($this->timezone ?? SerializableTimezone::UTC());
        $workout ??= [];
        $workoutName = is_string($workout['wkt_name'] ?? null) ? trim($workout['wkt_name']) : '';
        $workoutDescription = is_string($workout['wkt_description'] ?? null) ? trim($workout['wkt_description']) : '';
        $activityName = '' !== $workoutName ? ActivityName::fromString($workoutName) : ActivityName::from($startDateTime, $sportType);

        $activity = Activity::create(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::fromDeviceAndActivityName(
                deviceName: $deviceName,
                activityName: (string) $activityName
            ),
            importSource: ImportSource::FIT_FILE,
            externalReferenceId: ExternalReferenceId::fromString($file->getPath()->getFilename()),
            name: $activityName,
            description: '' !== $workoutDescription ? $workoutDescription : null,
            distance: Kilometer::from(round((is_numeric($session['total_distance'] ?? null) ? (float) $session['total_distance'] : 0.0) / 1000, 3)),
            elevation: Meter::from(round(is_numeric($session['total_ascent'] ?? null) ? (float) $session['total_ascent'] : StreamMath::elevationGain($streamMap[StreamType::ALTITUDE->value]))),
            startingCoordinate: $this->resolveStartingCoordinate($session, $streamMap),
            calories: is_numeric($session['total_calories'] ?? null) ? (int) round((float) $session['total_calories']) : null,
            kilojoules: null !== $work ? (int) round($work / 1000) : null,
            averagePower: $this->resolveAveragePower(
                session: $session,
                wattsStream: $streamMap[StreamType::WATTS->value]
            ),
            maxPower: is_numeric($session['max_power'] ?? null) ? (int) round((float) $session['max_power']) : Math::max($streamMap[StreamType::WATTS->value]),
            averageSpeed: $this->resolveAverageSpeed(
                session: $session,
                velocityStream: $streamMap[StreamType::VELOCITY->value]
            )->toKmPerHour(),
            maxSpeed: MetersPerSecond::fromOptional(is_numeric($session['enhanced_max_speed'] ?? $session['max_speed'] ?? null) ? (float) ($session['enhanced_max_speed'] ?? $session['max_speed'] ?? null) : Math::maxFloat($streamMap[StreamType::VELOCITY->value]))->toKmPerHour(),
            averageHeartRate: is_numeric($session['avg_heart_rate'] ?? null) ? (int) round((float) $session['avg_heart_rate']) : Math::average($streamMap[StreamType::HEART_RATE->value]),
            maxHeartRate: is_numeric($session['max_heart_rate'] ?? null) ? (int) round((float) $session['max_heart_rate']) : Math::max($streamMap[StreamType::HEART_RATE->value]),
            averageCadence: is_numeric($session['avg_cadence'] ?? null) ? (int) round((float) $session['avg_cadence']) : Math::average($streamMap[StreamType::CADENCE->value]),
            movingTimeInSeconds: is_numeric($session['total_timer_time'] ?? null) ? (int) round((float) $session['total_timer_time']) : 0,
            elapsedTimeInSeconds: is_numeric($session['total_elapsed_time'] ?? null) ? (int) round((float) $session['total_elapsed_time']) : 0,
            deviceName: $deviceName,
            connectedSensors: $this->resolveConnectedSensors($deviceInfoMessages),
            totalImageCount: 0,
            localImagePaths: [],
            polyline: StreamMath::encodePolyline($streamMap),
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: null,
            isCommute: false,
            workoutType: null,
        );

        return ParsedActivityFile::create(
            activity: $activity,
            streams: $this->activityStreamsMapper->fromStreamMap($streamMap, $activityId),
            laps: $this->activityLapsMapper->map($this->buildParsedLaps($lapMessages), $activityId),
        );
    }

    /**
     * @param list<array<string, mixed>> $lapMessages
     *
     * @return list<ParsedActivityLap>
     */
    private function buildParsedLaps(array $lapMessages): array
    {
        return array_map(
            function (array $lap, int $index): ParsedActivityLap {
                $averageHeartRate = $lap['avg_heart_rate'] ?? null;

                return ParsedActivityLap::create(
                    lapNumber: $index + 1,
                    name: sprintf('Lap %d', $index + 1),
                    elapsedTimeInSeconds: is_numeric($lap['total_elapsed_time'] ?? null) ? (int) round((float) $lap['total_elapsed_time']) : 0,
                    movingTimeInSeconds: is_numeric($lap['total_timer_time'] ?? null) ? (int) round((float) $lap['total_timer_time']) : 0,
                    distance: Meter::from(is_numeric($lap['total_distance'] ?? null) ? (float) $lap['total_distance'] : 0.0),
                    averageSpeed: MetersPerSecond::from($this->resolveLapAverageSpeed($lap)),
                    maxSpeed: MetersPerSecond::from(is_numeric($lap['enhanced_max_speed'] ?? $lap['max_speed'] ?? null) ? (float) ($lap['enhanced_max_speed'] ?? $lap['max_speed'] ?? null) : 0.0),
                    elevationDifference: Meter::from(is_numeric($lap['total_ascent'] ?? null) ? (float) $lap['total_ascent'] : 0.0),
                    averageHeartRate: empty($averageHeartRate) ? null : (int) round((float) $averageHeartRate),
                );
            },
            $lapMessages,
            array_keys($lapMessages),
        );
    }

    /**
     * @param array<string, mixed> $lap
     */
    private function resolveLapAverageSpeed(array $lap): float
    {
        if (is_numeric($lap['enhanced_avg_speed'] ?? $lap['avg_speed'] ?? null)) {
            return (float) ($lap['enhanced_avg_speed'] ?? $lap['avg_speed']);
        }

        // Laps without a speed summary (e.g. Huawei Health exports) still
        // carry distance and timer time; derive the average from those.
        $totalDistance = is_numeric($lap['total_distance'] ?? null) ? (float) $lap['total_distance'] : null;
        $totalTimerTime = is_numeric($lap['total_timer_time'] ?? null) ? (float) $lap['total_timer_time'] : null;
        if (null !== $totalDistance && null !== $totalTimerTime && $totalTimerTime > 0.0) {
            return $totalDistance / $totalTimerTime;
        }

        return 0.0;
    }

    /**
     * @param list<array<string, mixed>> $deviceInfoMessages
     */
    private function resolveConnectedSensors(array $deviceInfoMessages): ?ConnectedSensors
    {
        if ([] === $deviceInfoMessages) {
            return null;
        }

        return ConnectedSensors::fromSensors(...array_filter(array_map(
            $this->toConnectedSensor(...),
            $this->mergeDeviceInfoByDeviceIndex($deviceInfoMessages),
        )));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function toConnectedSensor(array $fields): ?ConnectedSensor
    {
        if (!($sensorType = FitDeviceType::resolveSensorType($fields)) instanceof \App\Domain\Gear\Sensor\SensorType) {
            return null;
        }
        if (null === $manufacturer = $this->toInt($fields['manufacturer'] ?? null)) {
            // Without a manufacturer there is nothing stable to identify the sensor by.
            return null;
        }

        $product = $this->toInt($fields['product'] ?? null);

        return ConnectedSensor::create(
            $manufacturer,
            $product,
            $this->toInt($fields['serial_number'] ?? null),
            (null !== $product ? FitProduct::name($manufacturer, $product) : null) ?? FitManufacturer::name($manufacturer),
            $sensorType,
        );
    }

    /**
     * @param list<array<string, mixed>> $deviceInfoMessages
     *
     * @return list<array<string, mixed>>
     */
    private function mergeDeviceInfoByDeviceIndex(array $deviceInfoMessages): array
    {
        $merged = [];

        foreach ($deviceInfoMessages as $position => $fields) {
            // A message without a device index cannot be tied to any other, so it is
            // kept on its own instead of being folded into a shared bucket.
            $deviceIndex = $this->toInt($fields['device_index'] ?? null);
            $key = null !== $deviceIndex ? 'index-'.$deviceIndex : 'unindexed-'.$position;

            foreach ($fields as $field => $value) {
                if (null === $value) {
                    continue;
                }
                $merged[$key][$field] ??= $value;
            }
        }

        return array_values($merged);
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * Some devices (e.g. Bosch eBike head units) split a single point in time
     * across several "record" messages, each carrying only a subset of fields
     * (one with speed/power, another with only heart rate, ...). Collapse
     * consecutive records that share a timestamp into one logical record.
     *
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    private function mergeRecordsByTimestamp(array $records): array
    {
        $merged = [];
        $previousTimestamp = null;

        foreach ($records as $record) {
            $timestamp = is_numeric($record['timestamp'] ?? null) ? (int) round((float) $record['timestamp']) : null;
            if ([] === $merged || null === $timestamp || $timestamp !== $previousTimestamp) {
                $merged[] = $record;
                $previousTimestamp = $timestamp;
                continue;
            }

            $target = array_key_last($merged);
            foreach ($record as $field => $value) {
                if (null !== $value) {
                    $merged[$target][$field] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return array<string, list<mixed>>
     */
    private function buildStreams(array $records, int $startTimestamp): array
    {
        $streams = [
            StreamType::TIME->value => [],
            StreamType::DISTANCE->value => [],
            StreamType::LAT_LNG->value => [],
            StreamType::ALTITUDE->value => [],
            StreamType::VELOCITY->value => [],
            StreamType::HEART_RATE->value => [],
            StreamType::CADENCE->value => [],
            StreamType::WATTS->value => [],
            StreamType::TEMP->value => [],
        ];

        foreach ($records as $record) {
            $timestamp = is_numeric($record['timestamp'] ?? null) ? (int) round((float) $record['timestamp']) : null;
            $streams[StreamType::TIME->value][] = null !== $timestamp ? $timestamp - $startTimestamp : null;
            $streams[StreamType::DISTANCE->value][] = is_numeric($record['distance'] ?? null) ? (float) $record['distance'] : null;

            $latitude = is_numeric($record['position_lat'] ?? null) ? (float) $record['position_lat'] : null;
            $longitude = is_numeric($record['position_long'] ?? null) ? (float) $record['position_long'] : null;
            $streams[StreamType::LAT_LNG->value][] = (null !== $latitude && null !== $longitude && (0.0 !== $latitude || 0.0 !== $longitude))
                ? [GeoMath::semicirclesToDegrees($latitude), GeoMath::semicirclesToDegrees($longitude)]
                : null;

            $streams[StreamType::ALTITUDE->value][] = is_numeric($record['enhanced_altitude'] ?? $record['altitude'] ?? null) ? (float) ($record['enhanced_altitude'] ?? $record['altitude'] ?? null) : null;
            $streams[StreamType::VELOCITY->value][] = is_numeric($record['enhanced_speed'] ?? $record['speed'] ?? null) ? (float) ($record['enhanced_speed'] ?? $record['speed'] ?? null) : null;
            $streams[StreamType::HEART_RATE->value][] = is_numeric($record['heart_rate'] ?? null) ? (int) round((float) $record['heart_rate']) : null;
            $streams[StreamType::CADENCE->value][] = is_numeric($record['cadence'] ?? null) ? (int) round((float) $record['cadence']) : null;
            $streams[StreamType::WATTS->value][] = is_numeric($record['power'] ?? null) ? (int) round((float) $record['power']) : null;
            $streams[StreamType::TEMP->value][] = is_numeric($record['temperature'] ?? null) ? (int) round((float) $record['temperature']) : null;
        }

        return $streams;
    }

    /**
     * Some exporters (e.g. Huawei Health via Health Sync) omit the session speed
     * summaries entirely; fall back to the FIT-spec definition of avg_speed
     * (total distance over timer time), then to the velocity stream.
     *
     * @param array<string, mixed> $session
     * @param list<mixed>          $velocityStream
     */
    private function resolveAverageSpeed(array $session, array $velocityStream): MetersPerSecond
    {
        if (is_numeric($session['enhanced_avg_speed'] ?? $session['avg_speed'] ?? null)) {
            return MetersPerSecond::from((float) ($session['enhanced_avg_speed'] ?? $session['avg_speed']));
        }

        $totalDistance = is_numeric($session['total_distance'] ?? null) ? (float) $session['total_distance'] : null;
        $totalTimerTime = is_numeric($session['total_timer_time'] ?? null) ? (float) $session['total_timer_time'] : null;
        if (null !== $totalDistance && null !== $totalTimerTime && $totalTimerTime > 0.0) {
            return MetersPerSecond::from($totalDistance / $totalTimerTime);
        }

        return MetersPerSecond::fromOptional(Math::averageFloat($velocityStream));
    }

    /**
     * @param array<string, mixed> $session
     * @param list<mixed>          $wattsStream
     */
    private function resolveAveragePower(array $session, array $wattsStream): ?int
    {
        $sessionAverage = is_numeric($session['avg_power'] ?? null) ? (int) round((float) $session['avg_power']) : null;
        $streamAverage = Math::average($wattsStream);

        if (null === $streamAverage || null === $sessionAverage) {
            return $sessionAverage ?? $streamAverage;
        }

        // Some head units (e.g. Bosch) exclude zero-power samples from the session
        // average. When it deviates too far from the stream average, trust the stream.
        if (abs($sessionAverage - $streamAverage) / max($streamAverage, 1) > self::MAX_AVG_POWER_DEVIANCE) {
            return $streamAverage;
        }

        return $sessionAverage;
    }

    /**
     * @param array<string, mixed>       $session
     * @param array<string, list<mixed>> $streams
     */
    private function resolveStartingCoordinate(array $session, array $streams): ?Coordinate
    {
        $latitude = is_numeric($session['start_position_lat'] ?? null) ? (float) $session['start_position_lat'] : null;
        $longitude = is_numeric($session['start_position_long'] ?? null) ? (float) $session['start_position_long'] : null;
        // Indoor/virtual activities (e.g. Zwift) leave the session start position
        // at 0/0 ("null island"); fall through to the first GPS record instead.
        if (null !== $latitude && null !== $longitude && (0.0 !== $latitude || 0.0 !== $longitude)) {
            return Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString((string) GeoMath::semicirclesToDegrees($latitude)),
                longitude: Longitude::fromString((string) GeoMath::semicirclesToDegrees($longitude)),
            );
        }

        return StreamMath::firstCoordinate($streams);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function fieldMap(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            if (!is_string($field['name'] ?? null)) {
                continue;
            }
            $map[$field['name']] = $field['value'] ?? null;
        }

        return $map;
    }
}
