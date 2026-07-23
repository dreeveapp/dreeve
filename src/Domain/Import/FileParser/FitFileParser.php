<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdFactory;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Lap\ActivityLapIdFactory;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Domain\Activity\Math;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Domain\Import\FileParser\Fit\FitManufacturer;
use App\Domain\Import\FileParser\Fit\FitProduct;
use App\Domain\Import\FileParser\Fit\FitSportType;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Geography\Polyline;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;

final readonly class FitFileParser implements ActivityFileParser
{
    // Seconds between the Unix epoch and the FIT epoch (1989-12-31 00:00:00 UTC).
    // FIT timestamps are stored as seconds since the FIT epoch.
    private const int FIT_EPOCH_OFFSET = 631065600;

    public function __construct(
        private ActivityIdFactory $activityIdFactory,
        private ActivityLapIdFactory $activityLapIdFactory,
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
        /** @var array<string, mixed>|null $session */
        $session = null;
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
                case 'session':
                    $session ??= $fields;
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
            subSport: $session['sub_sport'] ?? null
        );

        if (!$sportType instanceof SportType) {
            throw new CouldNotParseActivityFile(message: sprintf('Unsupported FIT sport %s (sub sport %s)', $session['sport'] ?? 'null', $session['sub_sport'] ?? 'null'), activityFile: $file);
        }

        $streamMap = $this->buildStreams(
            records: $this->mergeStrapHeartRate(
                records: $this->mergeRecordsByTimestamp($records),
                strapHrSamples: $this->expandStrapHeartRateSamples($hrMessages),
            ),
            startTimestamp: $startTimestamp
        );
        $activityId = $this->activityIdFactory->random();
        $work = is_numeric($session['total_work'] ?? null) ? (float) $session['total_work'] : null;
        $startDateTime = SerializableDateTime::fromTimestamp(self::FIT_EPOCH_OFFSET + $startTimestamp)->toTimezone($this->timezone ?? SerializableTimezone::UTC());

        $activity = Activity::fromState(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::fromDeviceAndActivityName(
                deviceName: $deviceName,
                activityName: ''
            ),
            importSource: ImportSource::FIT_FILE,
            externalReferenceId: ExternalReferenceId::fromString($file->getPath()->getFilename()),
            name: ActivityName::from($startDateTime, $sportType),
            description: null,
            distance: Kilometer::from(round((is_numeric($session['total_distance'] ?? null) ? (float) $session['total_distance'] : 0.0) / 1000, 3)),
            elevation: Meter::from(round(is_numeric($session['total_ascent'] ?? null) ? (float) $session['total_ascent'] : 0.0)),
            startingCoordinate: $this->resolveStartingCoordinate($session, $streamMap),
            calories: is_numeric($session['total_calories'] ?? null) ? (int) round((float) $session['total_calories']) : null,
            kilojoules: null !== $work ? (int) round($work / 1000) : null,
            averagePower: is_numeric($session['avg_power'] ?? null) ? (int) round((float) $session['avg_power']) : Math::average($streamMap[StreamType::WATTS->value]),
            maxPower: is_numeric($session['max_power'] ?? null) ? (int) round((float) $session['max_power']) : Math::max($streamMap[StreamType::WATTS->value]),
            averageSpeed: MetersPerSecond::fromOptional(is_numeric($session['enhanced_avg_speed'] ?? $session['avg_speed'] ?? null) ? (float) ($session['enhanced_avg_speed'] ?? $session['avg_speed'] ?? null) : null)->toKmPerHour(),
            maxSpeed: MetersPerSecond::fromOptional(is_numeric($session['enhanced_max_speed'] ?? $session['max_speed'] ?? null) ? (float) ($session['enhanced_max_speed'] ?? $session['max_speed'] ?? null) : null)->toKmPerHour(),
            averageHeartRate: is_numeric($session['avg_heart_rate'] ?? null) ? (int) round((float) $session['avg_heart_rate']) : Math::average($streamMap[StreamType::HEART_RATE->value]),
            maxHeartRate: is_numeric($session['max_heart_rate'] ?? null) ? (int) round((float) $session['max_heart_rate']) : Math::max($streamMap[StreamType::HEART_RATE->value]),
            averageCadence: is_numeric($session['avg_cadence'] ?? null) ? (int) round((float) $session['avg_cadence']) : Math::average($streamMap[StreamType::CADENCE->value]),
            movingTimeInSeconds: is_numeric($session['total_timer_time'] ?? null) ? (int) round((float) $session['total_timer_time']) : 0,
            elapsedTimeInSeconds: is_numeric($session['total_elapsed_time'] ?? null) ? (int) round((float) $session['total_elapsed_time']) : 0,
            deviceName: $deviceName,
            totalImageCount: 0,
            localImagePaths: [],
            polyline: $this->encodePolyline($streamMap),
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: null,
            isCommute: false,
            workoutType: null,
        );

        return ParsedActivityFile::create(
            activity: $activity,
            streams: $this->activityStreamsMapper->fromStreamMap($streamMap, $activityId),
            laps: $this->buildActivityLaps($lapMessages, $activityId),
        );
    }

    /**
     * @param list<array<string, mixed>> $lapMessages
     */
    private function buildActivityLaps(array $lapMessages, ActivityId $activityId): ActivityLaps
    {
        $averageSpeeds = array_map(
            static fn (array $lap): float => is_numeric($lap['enhanced_avg_speed'] ?? $lap['avg_speed'] ?? null)
                ? (float) ($lap['enhanced_avg_speed'] ?? $lap['avg_speed'] ?? null)
                : 0.0,
            $lapMessages
        );
        $minAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? min($averageSpeeds) : 0.0);
        $maxAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? max($averageSpeeds) : 0.0);

        $laps = ActivityLaps::empty();
        foreach ($lapMessages as $index => $lap) {
            $laps->add(ActivityLap::create(
                lapId: $this->activityLapIdFactory->random(),
                activityId: $activityId,
                lapNumber: $index + 1,
                name: sprintf('Lap %d', $index + 1),
                elapsedTimeInSeconds: is_numeric($lap['total_elapsed_time'] ?? null) ? (int) round((float) $lap['total_elapsed_time']) : 0,
                movingTimeInSeconds: is_numeric($lap['total_timer_time'] ?? null) ? (int) round((float) $lap['total_timer_time']) : 0,
                distance: Meter::from(is_numeric($lap['total_distance'] ?? null) ? (float) $lap['total_distance'] : 0.0),
                averageSpeed: MetersPerSecond::from($averageSpeeds[$index]),
                minAverageSpeed: $minAverageSpeed,
                maxAverageSpeed: $maxAverageSpeed,
                maxSpeed: MetersPerSecond::from(is_numeric($lap['enhanced_max_speed'] ?? $lap['max_speed'] ?? null) ? (float) ($lap['enhanced_max_speed'] ?? $lap['max_speed'] ?? null) : 0.0),
                elevationDifference: Meter::from(is_numeric($lap['total_ascent'] ?? null) ? (float) $lap['total_ascent'] : 0.0),
                averageHeartRate: empty($lap['avg_heart_rate']) ? null : (int) round((float) $lap['avg_heart_rate']),
            ));
        }

        return $laps;
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
     * Chest straps cannot broadcast heart rate through water. During swims the
     * strap stores its samples and the watch downloads them afterwards into
     * "hr" messages, while the "record" messages only carry the (far less
     * accurate) wrist reading. Each hr message batches filtered_bpm samples
     * paired with event_timestamp values.
     *
     * @param list<array<string, mixed>> $hrMessages
     *
     * @return list<array{float, int}> chronological [timestamp in seconds since the FIT epoch, bpm] pairs
     */
    private function expandStrapHeartRateSamples(array $hrMessages): array
    {
        $anchorTimestamp = null;
        $anchorEventTimestamp = null;
        /** @var list<array{float, int}> $samples */
        $samples = [];

        foreach ($hrMessages as $hrMessage) {
            $eventTimestamps = array_values(is_array($hrMessage['event_timestamp'] ?? null) ? $hrMessage['event_timestamp'] : [$hrMessage['event_timestamp'] ?? null]);
            $bpms = array_values(is_array($hrMessage['filtered_bpm'] ?? null) ? $hrMessage['filtered_bpm'] : [$hrMessage['filtered_bpm'] ?? null]);

            if (is_numeric($hrMessage['timestamp'] ?? null) && 1 === count($eventTimestamps) && is_numeric($eventTimestamps[0] ?? null)) {
                $anchorTimestamp = (float) $hrMessage['timestamp'] + (is_numeric($hrMessage['fractional_timestamp'] ?? null) ? (float) $hrMessage['fractional_timestamp'] : 0.0);
                $anchorEventTimestamp = (float) $eventTimestamps[0];
            }

            if (null === $anchorTimestamp || null === $anchorEventTimestamp || count($eventTimestamps) !== count($bpms)) {
                continue;
            }

            foreach ($eventTimestamps as $index => $eventTimestamp) {
                $bpm = $bpms[$index];
                if (!is_numeric($eventTimestamp) || !is_numeric($bpm)) {
                    continue;
                }
                $bpm = (int) round((float) $bpm);
                if ($bpm <= 0 || $bpm >= 255) {
                    // 0xFF is the FIT "invalid" sentinel for uint8 fields.
                    continue;
                }
                $eventTimestamp = (float) $eventTimestamp;
                if ($eventTimestamp < $anchorEventTimestamp) {
                    // The 32 bit event_timestamp counter has a 1/1024s resolution, so it wraps every 2^32/1024 seconds.
                    $eventTimestamp += 4194304.0;
                }

                $timestamp = $anchorTimestamp + ($eventTimestamp - $anchorEventTimestamp);

                // Carry the previous sample forward across gaps in 250ms increments, capped at 5s.
                if ([] !== $samples) {
                    [$previousTimestamp, $previousBpm] = $samples[array_key_last($samples)];
                    $gap = $timestamp - $previousTimestamp;
                    for ($step = 1; $gap > 0.25 && $step <= 20; ++$step) {
                        $samples[] = [$previousTimestamp + $step * 0.25, $previousBpm];
                        $gap -= 0.25;
                    }
                }

                $samples[] = [$timestamp, $bpm];
            }
        }

        return $samples;
    }

    /**
     * Overwrite the (wrist based) heart_rate of each record with the average
     * of the strap samples taken between the previous record and this one.
     * Records without nearby strap samples keep their wrist reading. Mirrors
     * Garmin's reference implementation (HrMesgUtils.mergeHeartRates in the
     * FIT SDK).
     *
     * @param list<array<string, mixed>> $records
     * @param list<array{float, int}>    $strapHrSamples
     *
     * @return list<array<string, mixed>>
     */
    private function mergeStrapHeartRate(array $records, array $strapHrSamples): array
    {
        if ([] === $strapHrSamples) {
            return $records;
        }

        $sampleIndex = 0;
        $countSamples = count($strapHrSamples);
        $rangeStart = null;

        foreach ($records as $key => $record) {
            if (!is_numeric($record['timestamp'] ?? null)) {
                continue;
            }
            $rangeEnd = (float) $record['timestamp'];
            if (null === $rangeStart || $rangeStart >= $rangeEnd) {
                $rangeStart = $rangeEnd - 1.0;
                $sampleIndex = max(0, $sampleIndex - 1);
            }

            $sum = 0;
            $count = 0;
            while ($sampleIndex < $countSamples) {
                [$timestamp, $bpm] = $strapHrSamples[$sampleIndex];
                if ($timestamp > $rangeEnd) {
                    break;
                }
                if ($timestamp > $rangeStart) {
                    $sum += $bpm;
                    ++$count;
                }
                ++$sampleIndex;
            }

            if ($count > 0) {
                $records[$key]['heart_rate'] = (int) round($sum / $count);
            }
            $rangeStart = $rangeEnd;
        }

        return $records;
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

        foreach ($streams[StreamType::LAT_LNG->value] ?? [] as $point) {
            if (is_array($point)) {
                return Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString((string) $point[0]),
                    longitude: Longitude::fromString((string) $point[1]),
                );
            }
        }

        return null;
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

    /**
     * @param array<string, list<mixed>> $streamMap
     */
    private function encodePolyline(array $streamMap): ?string
    {
        /** @var array<int, array{float, float}> $coordinates */
        $coordinates = array_values(array_filter(
            $streamMap[StreamType::LAT_LNG->value] ?? [],
            is_array(...),
        ));

        if ([] === $coordinates) {
            return null;
        }

        return (string) Polyline::fromCoordinates($coordinates)->simplify()->encode();
    }
}
