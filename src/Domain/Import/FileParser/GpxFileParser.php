<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIdFactory;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Math;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\Shifting\ActivityGearUsages;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Domain\Import\FileParser\Gpx\GpxMetadata;
use App\Domain\Import\FileParser\Gpx\GpxSportType;
use App\Domain\Import\FileParser\Gpx\GpxWorkoutSummary;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;

final readonly class GpxFileParser implements ActivityFileParser
{
    private const float ELEVATION_MIN = -9999.99;
    private const float ELEVATION_MAX = 9999.99;

    public function __construct(
        private ActivityIdFactory $activityIdFactory,
        private ActivityLapsMapper $activityLapsMapper,
        private ActivityStreamsMapper $activityStreamsMapper,
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

        $sportType = $this->resolveSportType($xml);
        $deviceName = null;
        if (isset($xml['creator']) && '' !== (string) $xml['creator']) {
            $deviceName = (string) $xml['creator'];
        }
        $metadata = GpxMetadata::fromXml($xml);
        $workoutSummary = null !== $metadata->getName() ? GpxWorkoutSummary::tryFromString($metadata->getName()) : null;
        $calories = $this->resolveCalories($xml) ?? $workoutSummary?->getCalories();

        [$laps, $streams, $startTimestamp] = $this->parseTracksAndStreams($xml);

        if (null === $startTimestamp) {
            throw new CouldNotParseActivityFile(message: sprintf('No trackpoints with a timestamp found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $velocities = array_filter($streams[StreamType::VELOCITY->value], static fn (mixed $v): bool => null !== $v);
        $activityId = $this->activityIdFactory->random();
        $startDateTime = SerializableDateTime::fromTimestamp($startTimestamp)->toTimezone($this->timezone ?? SerializableTimezone::UTC());
        $activityLaps = $this->activityLapsMapper->map($laps, $activityId);

        $distanceInMeter = $workoutSummary?->getDistanceInMeter()
            ?? $activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getDistance()->toFloat());
        $elapsedTimeInSeconds = $workoutSummary?->getElapsedTimeInSeconds()
            ?? (int) $activityLaps->sum(static fn (ActivityLap $lap): int => $lap->getElapsedTimeInSeconds());
        $movingTimeInSeconds = $workoutSummary?->getMovingTimeInSeconds()
            ?? (int) $activityLaps->sum(static fn (ActivityLap $lap): int => $lap->getMovingTimeInSeconds());
        if ($elapsedTimeInSeconds > 0) {
            $movingTimeInSeconds = min($movingTimeInSeconds, $elapsedTimeInSeconds);
        }
        $activityName = $this->resolveActivityName(
            xml: $xml,
            metadata: $metadata,
            workoutSummary: $workoutSummary,
            startDateTime: $startDateTime,
            sportType: $sportType
        );

        $activity = Activity::create(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::fromDeviceAndActivityName(
                deviceName: $deviceName,
                activityName: $file->getPath()->getFilename()
            ),
            importSource: ImportSource::GPX_FILE,
            externalReferenceId: ExternalReferenceId::fromString($file->getPath()->getFilename()),
            name: $activityName,
            description: $workoutSummary?->getDescription() ?? $metadata->getDescription() ?? $this->resolveTrackDescription($xml),
            distance: Kilometer::from(round($distanceInMeter / 1000, 3)),
            elevation: Meter::from(round($activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getElevationDifference()->toFloat()))),
            startingCoordinate: StreamMath::firstCoordinate($streams),
            calories: $calories,
            kilojoules: null,
            averagePower: Math::average($streams[StreamType::WATTS->value]),
            maxPower: Math::max($streams[StreamType::WATTS->value]),
            averageSpeed: MetersPerSecond::fromOptional(match (true) {
                null !== $workoutSummary?->getDistanceInMeter() && $movingTimeInSeconds > 0 => $distanceInMeter / $movingTimeInSeconds,
                [] !== $velocities => array_sum($velocities) / count($velocities),
                default => null,
            })->toKmPerHour(),
            maxSpeed: MetersPerSecond::fromOptional([] !== $velocities ? max($velocities) : null)->toKmPerHour(),
            averageHeartRate: Math::average($streams[StreamType::HEART_RATE->value]),
            maxHeartRate: Math::max($streams[StreamType::HEART_RATE->value]),
            averageCadence: Math::average($streams[StreamType::CADENCE->value]),
            movingTimeInSeconds: $movingTimeInSeconds,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            deviceName: $deviceName,
            connectedSensors: null,
            totalImageCount: 0,
            localImagePaths: [],
            polyline: StreamMath::encodePolyline($streams),
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: null,
            isCommute: false,
            isGroupActivity: false,
            workoutType: null,
        );

        return ParsedActivityFile::create(
            activity: $activity,
            streams: $this->activityStreamsMapper->fromStreamMap($streams, $activityId),
            laps: $activityLaps,
            gearUsages: ActivityGearUsages::empty(),
        );
    }

    /**
     * @return array{list<ParsedActivityLap>, array<string, list<mixed>>, ?int}
     */
    private function parseTracksAndStreams(\SimpleXMLElement $xml): array
    {
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
            StreamType::TEMP->value => [],
        ];
        $laps = [];

        $lapIndex = 0;
        foreach ($xml->trk as $track) {
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
                    $rawTime = $this->stringChild($trackpoint, 'time');
                    if (null === $rawTime) {
                        continue;
                    }
                    if ('' === $rawTime) {
                        continue;
                    }
                    $time = SerializableDateTime::fromString($rawTime)->getTimestamp();
                    $startTimestamp ??= $time;

                    $latitude = isset($trackpoint['lat']) ? (float) $trackpoint['lat'] : null;
                    $longitude = isset($trackpoint['lon']) ? (float) $trackpoint['lon'] : null;
                    if (0.0 === $latitude && 0.0 === $longitude) {
                        $latitude = null;
                        $longitude = null;
                    }
                    $altitude = $this->sanitizeElevation($this->floatChild($trackpoint, 'ele'));

                    $instantSpeed = null;
                    if (!in_array(null, [$previousLatitude, $previousLongitude, $latitude, $longitude], true)) {
                        $delta = GeoMath::haversineDistance(
                            lat1: $previousLatitude,
                            lon1: $previousLongitude,
                            lat2: $latitude,
                            lon2: $longitude
                        );
                        $cumulativeDistance += $delta;
                        $segmentDistance += $delta;

                        if (null !== $previousTime && $time > $previousTime) {
                            $instantSpeed = $delta / ($time - $previousTime);
                        }
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
                    $streams[StreamType::TEMP->value][] = $extensions['temp'];

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
                    elevationGain: StreamMath::elevationGain($segmentAltitudes),
                    activeSeconds: StreamMath::activeSeconds($segmentTimes),
                );
            }
        }

        return [$laps, $streams, $startTimestamp];
    }

    private function resolveSportType(\SimpleXMLElement $xml): SportType
    {
        foreach ($xml->trk as $track) {
            $type = $this->stringChild($track, 'type');
            if (null !== $type && '' !== $type) {
                return GpxSportType::resolve($type);
            }
        }

        return SportType::WORKOUT;
    }

    private function resolveActivityName(
        \SimpleXMLElement $xml,
        GpxMetadata $metadata,
        ?GpxWorkoutSummary $workoutSummary,
        SerializableDateTime $startDateTime,
        SportType $sportType,
    ): ActivityName {
        // A serialized workout summary is not a name, even though it sits in the name element.
        $name = $workoutSummary instanceof GpxWorkoutSummary ? null : $metadata->getName();
        $name ??= $this->firstNonEmptyTrackChild($xml, 'name');

        return null !== $name ? ActivityName::fromString($name) : ActivityName::from($startDateTime, $sportType);
    }

    private function resolveTrackDescription(\SimpleXMLElement $xml): ?string
    {
        return $this->firstNonEmptyTrackChild($xml, 'desc');
    }

    private function firstNonEmptyTrackChild(\SimpleXMLElement $xml, string $child): ?string
    {
        foreach ($xml->trk as $track) {
            $value = trim($this->stringChild($track, $child) ?? '');
            if ('' !== $value) {
                return $value;
            }
        }

        return null;
    }

    private function resolveCalories(\SimpleXMLElement $xml): ?int
    {
        $calories = null;
        foreach ($xml->trk as $track) {
            if (null !== ($trackCalories = $this->sumCalories($track))) {
                $calories = ($calories ?? 0) + $trackCalories;
            }
        }

        return $calories;
    }

    /**
     * @param list<int>   $times
     * @param list<float> $speeds
     * @param list<int>   $heartRates
     */
    private function buildLap(int $index, array $times, float $distance, array $speeds, array $heartRates, float $elevationGain, int $activeSeconds): ParsedActivityLap
    {
        $elapsed = [] !== $times ? max($times) - min($times) : 0;
        // Elapsed time keeps the segment's full duration. Moving time is capped at the active time.
        $movingTime = min($elapsed, $activeSeconds);
        $averageHeartRate = [] !== $heartRates ? array_sum($heartRates) / count($heartRates) : null;

        return ParsedActivityLap::create(
            lapNumber: $index + 1,
            name: sprintf('Lap %d', $index + 1),
            elapsedTimeInSeconds: $elapsed,
            movingTimeInSeconds: $movingTime,
            distance: Meter::from($distance),
            averageSpeed: MetersPerSecond::from($movingTime > 0 ? $distance / $movingTime : 0.0),
            maxSpeed: MetersPerSecond::from([] !== $speeds ? max($speeds) : 0.0),
            elevationDifference: Meter::from($elevationGain),
            averageHeartRate: empty($averageHeartRate) ? null : (int) round($averageHeartRate),
        );
    }

    private function stringChild(\SimpleXMLElement $parent, string $child): ?string
    {
        return property_exists($parent, $child) && null !== $parent->{$child} ? (string) $parent->{$child} : null;
    }

    private function floatChild(\SimpleXMLElement $parent, string $child): ?float
    {
        return null !== ($value = $this->stringChild($parent, $child)) ? (float) $value : null;
    }

    /**
     * @return array{hr: ?int, cad: ?int, power: ?int, temp: ?int}
     */
    private function extractExtensionValues(\SimpleXMLElement $trackpoint): array
    {
        $values = ['hr' => null, 'cad' => null, 'power' => null, 'temp' => null];
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
     * @param array{hr: ?int, cad: ?int, power: ?int, temp: ?int} $values
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
                } elseif (in_array($tag, ['atemp', 'temp', 'temperature'], true)) {
                    $values['temp'] = $intValue;
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
}
