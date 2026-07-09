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
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\Time\Clock\Clock;
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

final readonly class GpxFileParser implements ActivityFileParser
{
    private const float ELEVATION_MIN = -9999.99;
    private const float ELEVATION_MAX = 9999.99;

    public function __construct(
        private ActivityIdFactory $activityIdFactory,
        private ActivityLapIdFactory $activityLapIdFactory,
        private Clock $clock,
        private ?SerializableTimezone $timezone,
    ) {
    }

    public function supportedExtension(): SupportedFileExtension
    {
        return SupportedFileExtension::GPX;
    }

    public function parse(RawActivityFile $file): ParsedActivityFile
    {
        $contents = $file->getContents();
        if ('' === trim($contents)) {
            throw new CouldNotParseActivityFile(message: sprintf('Could not read "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        // Strip namespace declarations and prefixes so SimpleXML element access is uniform
        // regardless of the file's (default + TrackPointExtension/gpxtpx) namespaces.
        $contents = (string) preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $contents);
        $contents = (string) preg_replace('/(<\/?)\w+:/', '$1', $contents);

        $previousErrorState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_use_internal_errors($previousErrorState);

        if (false === $xml) {
            throw new CouldNotParseActivityFile(message: sprintf('"%s" is not valid GPX XML', $file->getPath()->getFilename()), activityFile: $file);
        }

        if (!property_exists($xml, 'trk') || null === $xml->trk) {
            throw new CouldNotParseActivityFile(message: sprintf('No <trk> found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $startTimestamp = null;
        $cumulativeDistance = 0.0;

        $streams = [
            StreamType::TIME->value => [],
            StreamType::DISTANCE->value => [],
            StreamType::LAT_LNG->value => [],
            StreamType::ALTITUDE->value => [],
            StreamType::VELOCITY->value => [],
            StreamType::HEART_RATE->value => [],
            StreamType::CADENCE->value => [],
            StreamType::WATTS->value => [],
        ];
        $laps = [];

        $sportType = SportType::WORKOUT;
        $deviceName = $this->resolveDeviceName($xml);
        $calories = null;

        $lapIndex = 0;
        foreach ($xml->trk as $track) {
            if (property_exists($track, 'type') && null !== $track->type && '' !== (string) $track->type) {
                $sportType = $this->mapSportType((string) $track->type);
            }
            $trackCalories = $this->sumCalories($track);
            if (null !== $trackCalories) {
                $calories = ($calories ?? 0) + $trackCalories;
            }

            foreach ($track->trkseg ?? [] as $segment) {
                // Cursors must not carry across segments.
                $previousLatitude = null;
                $previousLongitude = null;
                $previousTime = null;

                $segmentTimes = [];
                $segmentSpeeds = [];
                $segmentAltitudes = [];
                $segmentHeartRates = [];
                $segmentDistance = 0.0;

                foreach ($segment->trkpt ?? [] as $trackpoint) {
                    // Skip trackpoints without time (e.g. OsmAnd exports).
                    if (!property_exists($trackpoint, 'time')) {
                        continue;
                    }
                    if (null === $trackpoint->time) {
                        continue;
                    }
                    if ('' === (string) $trackpoint->time) {
                        continue;
                    }
                    $time = SerializableDateTime::fromString((string) $trackpoint->time)->getTimestamp();
                    $startTimestamp ??= $time;

                    $latitude = isset($trackpoint['lat']) ? (float) $trackpoint['lat'] : null;
                    $longitude = isset($trackpoint['lon']) ? (float) $trackpoint['lon'] : null;
                    $altitude = $this->sanitizeElevation(property_exists($trackpoint, 'ele') && null !== $trackpoint->ele ? (float) $trackpoint->ele : null);

                    if (!in_array(null, [$previousLatitude, $previousLongitude, $latitude, $longitude], true)) {
                        $delta = GeoMath::haversineDistance(
                            lat1: $previousLatitude,
                            lon1: $previousLongitude,
                            lat2: $latitude,
                            lon2: $longitude
                        );
                        $cumulativeDistance += $delta;
                        $segmentDistance += $delta;
                    }

                    $instantSpeed = null;
                    if (null !== $previousTime && $time > $previousTime && null !== $latitude && null !== $longitude && null !== $previousLatitude && null !== $previousLongitude) {
                        $instantSpeed = GeoMath::haversineDistance(
                            lat1: $previousLatitude,
                            lon1: $previousLongitude,
                            lat2: $latitude,
                            lon2: $longitude
                        ) / ($time - $previousTime);
                    }

                    $extensions = $this->extractExtensionValues($trackpoint);

                    $streams[StreamType::TIME->value][] = $time - $startTimestamp;
                    $streams[StreamType::DISTANCE->value][] = round($cumulativeDistance, 2);
                    $streams[StreamType::LAT_LNG->value][] = (null !== $latitude && null !== $longitude) ? [$latitude, $longitude] : null;
                    $streams[StreamType::ALTITUDE->value][] = $altitude;
                    $streams[StreamType::VELOCITY->value][] = $instantSpeed;
                    $streams[StreamType::HEART_RATE->value][] = $extensions['hr'];
                    $streams[StreamType::CADENCE->value][] = $extensions['cad'];
                    $streams[StreamType::WATTS->value][] = $extensions['power'];

                    $segmentTimes[] = $time;
                    $segmentAltitudes[] = $altitude;
                    if (null !== $instantSpeed) {
                        $segmentSpeeds[] = $instantSpeed;
                    }
                    if (null !== $extensions['hr']) {
                        $segmentHeartRates[] = $extensions['hr'];
                    }

                    $previousLatitude = $latitude;
                    $previousLongitude = $longitude;
                    $previousTime = $time;
                }

                if (count($segmentTimes) < 2) {
                    continue;
                }

                $laps[] = $this->buildLap(
                    index: $lapIndex++,
                    times: $segmentTimes,
                    distance: $segmentDistance,
                    speeds: $segmentSpeeds,
                    heartRates: $segmentHeartRates,
                    elevationGain: $this->elevationGain($segmentAltitudes),
                );
            }
        }

        if (null === $startTimestamp) {
            throw new CouldNotParseActivityFile(message: sprintf('No trackpoints with a timestamp found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $velocities = array_filter($streams[StreamType::VELOCITY->value], static fn (mixed $v): bool => null !== $v);
        $activityId = $this->activityIdFactory->random();
        $startDateTime = SerializableDateTime::fromTimestamp($startTimestamp)->toTimezone($this->timezone ?? SerializableTimezone::UTC());
        $activityLaps = $this->buildActivityLaps($laps, $activityId);
        $activity = Activity::fromState(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::fromDeviceAndActivityName(
                deviceName: $deviceName,
                activityName: $file->getPath()->getFilename()
            ),
            importSource: ImportSource::GPX_FILE,
            externalReferenceId: ExternalReferenceId::fromString($file->getPath()->getFilename()),
            name: ActivityName::from($startDateTime, $sportType),
            description: null,
            distance: Kilometer::from(round($activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getDistance()->toFloat()) / 1000, 3)),
            elevation: Meter::from(round($activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getElevationDifference()->toFloat()))),
            startingCoordinate: $this->resolveStartingCoordinate($streams),
            calories: $calories,
            kilojoules: null,
            averagePower: Math::average($streams[StreamType::WATTS->value]),
            maxPower: Math::max($streams[StreamType::WATTS->value]),
            averageSpeed: MetersPerSecond::fromOptional([] !== $velocities ? array_sum($velocities) / count($velocities) : null)->toKmPerHour(),
            maxSpeed: MetersPerSecond::fromOptional([] !== $velocities ? max($velocities) : null)->toKmPerHour(),
            averageHeartRate: Math::average($streams[StreamType::HEART_RATE->value]),
            maxHeartRate: Math::max($streams[StreamType::HEART_RATE->value]),
            averageCadence: Math::average($streams[StreamType::CADENCE->value]),
            movingTimeInSeconds: (int) $activityLaps->sum(static fn (ActivityLap $lap): int => $lap->getMovingTimeInSeconds()),
            elapsedTimeInSeconds: (int) $activityLaps->sum(static fn (ActivityLap $lap): int => $lap->getElapsedTimeInSeconds()),
            deviceName: $deviceName,
            totalImageCount: 0,
            localImagePaths: [],
            polyline: $this->encodePolyline($streams),
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: null,
            isCommute: false,
            workoutType: null,
        );

        return ParsedActivityFile::create(
            activity: $activity,
            streams: $this->buildActivityStreams($streams, $activityId),
            laps: $activityLaps,
        );
    }

    /**
     * @param array<string, list<mixed>> $streamMap
     */
    private function buildActivityStreams(array $streamMap, ActivityId $activityId): ActivityStreams
    {
        $createdOn = $this->clock->getCurrentDateTimeImmutable();

        $streams = ActivityStreams::empty();
        foreach ($streamMap as $type => $values) {
            if (!$streamType = StreamType::tryFrom($type)) {
                continue;
            }
            if ([] === array_filter($values, static fn (mixed $value): bool => null !== $value)) {
                continue;
            }
            $streams->add(ActivityStream::create(
                activityId: $activityId,
                streamType: $streamType,
                streamData: $values,
                createdOn: $createdOn,
            ));
        }

        return $streams;
    }

    /**
     * @param list<array<string, mixed>> $rawLaps
     */
    private function buildActivityLaps(array $rawLaps, ActivityId $activityId): ActivityLaps
    {
        $averageSpeeds = array_map(static fn (array $lap): float => (float) ($lap['average_speed'] ?? 0.0), $rawLaps);
        $minAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? min($averageSpeeds) : 0.0);
        $maxAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? max($averageSpeeds) : 0.0);

        $laps = ActivityLaps::empty();
        foreach ($rawLaps as $lap) {
            $laps->add(ActivityLap::create(
                lapId: $this->activityLapIdFactory->random(),
                activityId: $activityId,
                lapNumber: (int) $lap['lap_index'],
                name: (string) $lap['name'],
                elapsedTimeInSeconds: (int) $lap['elapsed_time'],
                movingTimeInSeconds: (int) $lap['moving_time'],
                distance: Meter::from((float) $lap['distance']),
                averageSpeed: MetersPerSecond::from((float) $lap['average_speed']),
                minAverageSpeed: $minAverageSpeed,
                maxAverageSpeed: $maxAverageSpeed,
                maxSpeed: MetersPerSecond::from((float) $lap['max_speed']),
                elevationDifference: Meter::from((float) ($lap['total_elevation_gain'] ?? 0)),
                averageHeartRate: empty($lap['average_heartrate']) ? null : (int) round((float) $lap['average_heartrate']),
            ));
        }

        return $laps;
    }

    /**
     * @param list<int>   $times
     * @param list<float> $speeds
     * @param list<int>   $heartRates
     *
     * @return array<string, mixed>
     */
    private function buildLap(int $index, array $times, float $distance, array $speeds, array $heartRates, float $elevationGain): array
    {
        $elapsed = [] !== $times ? max($times) - min($times) : 0;

        return [
            'id' => $index + 1,
            'lap_index' => $index + 1,
            'name' => sprintf('Lap %d', $index + 1),
            'elapsed_time' => $elapsed,
            'moving_time' => $elapsed,
            'distance' => $distance,
            'average_speed' => $elapsed > 0 ? $distance / $elapsed : 0.0,
            'max_speed' => [] !== $speeds ? max($speeds) : 0.0,
            'total_elevation_gain' => $elevationGain,
            'average_heartrate' => [] !== $heartRates ? array_sum($heartRates) / count($heartRates) : null,
        ];
    }

    /**
     * @param array<string, list<mixed>> $streams
     */
    private function resolveStartingCoordinate(array $streams): ?Coordinate
    {
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
     * @return array{hr: ?int, cad: ?int, power: ?int}
     */
    private function extractExtensionValues(\SimpleXMLElement $trackpoint): array
    {
        $values = ['hr' => null, 'cad' => null, 'power' => null];
        if (!property_exists($trackpoint, 'extensions') || null === $trackpoint->extensions) {
            return $values;
        }

        $this->collectExtensionValues(
            element: $trackpoint->extensions,
            values: $values
        );

        return $values;
    }

    /**
     * @param array{hr: ?int, cad: ?int, power: ?int} $values
     */
    private function collectExtensionValues(\SimpleXMLElement $element, array &$values): void
    {
        foreach ($element->children() as $name => $child) {
            $tag = strtolower($name);
            $text = trim((string) $child);

            if ('' !== $text && is_numeric($text)) {
                $intValue = (int) round((float) $text);
                if (in_array($tag, ['hr', 'heartrate', 'heart_rate'], true)) {
                    $values['hr'] = $intValue;
                } elseif (in_array($tag, ['cad', 'cadence'], true)) {
                    $values['cad'] = $intValue;
                } elseif (in_array($tag, ['power', 'powerinwatts'], true)) {
                    $values['power'] = $intValue;
                }
            }

            if (0 < $child->count()) {
                $this->collectExtensionValues(
                    element: $child,
                    values: $values
                );
            }
        }
    }

    private function sumCalories(\SimpleXMLElement $track): ?int
    {
        if (!property_exists($track, 'extensions') || null === $track->extensions) {
            return null;
        }

        $calories = null;
        $this->collectCalories($track->extensions, $calories);

        return $calories;
    }

    private function collectCalories(\SimpleXMLElement $element, ?int &$calories): void
    {
        foreach ($element->children() as $name => $child) {
            $text = trim((string) $child);
            if ('calories' === strtolower($name) && '' !== $text && is_numeric($text)) {
                $calories = ($calories ?? 0) + (int) round((float) $text);
            }
            if (0 < $child->count()) {
                $this->collectCalories($child, $calories);
            }
        }
    }

    private function sanitizeElevation(?float $elevation): ?float
    {
        if (null === $elevation || !is_finite($elevation)) {
            return null;
        }

        if (self::ELEVATION_MIN <= $elevation && $elevation <= self::ELEVATION_MAX) {
            return $elevation;
        }

        return null;
    }

    /**
     * @param list<?float> $altitudes
     */
    private function elevationGain(array $altitudes): float
    {
        $gain = 0.0;
        $previous = null;
        foreach ($altitudes as $altitude) {
            if (null === $altitude) {
                continue;
            }
            if (null !== $previous && $altitude > $previous) {
                $gain += $altitude - $previous;
            }
            $previous = $altitude;
        }

        return $gain;
    }

    private function resolveDeviceName(\SimpleXMLElement $xml): ?string
    {
        if (isset($xml['creator']) && '' !== (string) $xml['creator']) {
            return (string) $xml['creator'];
        }

        return null;
    }

    private function mapSportType(string $type): SportType
    {
        return match (strtolower($type)) {
            'running', 'run', '9' => SportType::RUN,
            'cycling', 'biking', 'ride', '1' => SportType::RIDE,
            'walking', 'walk' => SportType::WALK,
            'hiking', 'hike' => SportType::HIKE,
            'swimming', 'swim' => SportType::SWIM,
            default => SportType::tryFrom($type) ?? SportType::WORKOUT,
        };
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
