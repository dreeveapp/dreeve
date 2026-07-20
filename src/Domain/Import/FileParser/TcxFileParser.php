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
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Geography\Polyline;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;

final readonly class TcxFileParser implements ActivityFileParser
{
    // Interval longer than this is treated as a recording gap rather than active time.
    private const int MAX_RECORDING_GAP_IN_SECONDS = 60;

    public function __construct(
        private ActivityIdFactory $activityIdFactory,
        private ActivityLapIdFactory $activityLapIdFactory,
        private ActivityStreamsMapper $activityStreamsMapper,
        private ?SerializableTimezone $timezone,
    ) {
    }

    public function supportedExtension(): SupportedFileExtension
    {
        return SupportedFileExtension::TCX;
    }

    public function parse(RawActivityFile $file): ParsedActivityFile
    {
        $contents = $file->getContents();
        if ('' === trim($contents)) {
            throw new CouldNotParseActivityFile(message: sprintf('Could not read "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        // Strip namespace declarations and prefixes so SimpleXML element access is uniform
        // regardless of the file's (default + ActivityExtension) namespaces.
        $contents = (string) preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $contents);
        $contents = (string) preg_replace('/(<\/?)\w+:/', '$1', $contents);

        $previousErrorState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_use_internal_errors($previousErrorState);

        if (false === $xml) {
            throw new CouldNotParseActivityFile(message: sprintf('"%s" is not valid TCX XML', $file->getPath()->getFilename()), activityFile: $file);
        }

        $activityXml = $xml->Activities->Activity ?? null;
        if (null === $activityXml) {
            throw new CouldNotParseActivityFile(message: sprintf('No <Activity> found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $sportType = SportTypeName::tryResolve((string) $activityXml['Sport']) ?? SportType::WORKOUT;
        $deviceName = $this->stringChild($activityXml->Creator, 'Name');

        [$laps, $streams, $startTimestamp] = $this->parseLapsAndStreams($activityXml);

        if (null === $startTimestamp) {
            throw new CouldNotParseActivityFile(message: sprintf('No trackpoints with a timestamp found in "%s"', $file->getPath()->getFilename()), activityFile: $file);
        }

        $velocities = array_filter($streams[StreamType::VELOCITY->value], static fn (mixed $v): bool => null !== $v);
        if ([] === $velocities) {
            // Files without per-trackpoint TPX/Speed (e.g. Polar exports) still carry
            // cumulative distance + time per trackpoint; derive velocity from those.
            $streams[StreamType::VELOCITY->value] = $this->deriveVelocityStream(
                $streams[StreamType::DISTANCE->value],
                $streams[StreamType::TIME->value],
            );
            $velocities = array_filter($streams[StreamType::VELOCITY->value], static fn (mixed $v): bool => null !== $v);
        }

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
            importSource: ImportSource::TCX_FILE,
            externalReferenceId: ExternalReferenceId::fromString($file->getPath()->getFilename()),
            name: ActivityName::from($startDateTime, $sportType),
            description: null,
            distance: Kilometer::from(round($activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getDistance()->toFloat()) / 1000, 3)),
            elevation: Meter::from(round($activityLaps->sum(static fn (ActivityLap $lap): float => $lap->getElevationDifference()->toFloat()))),
            startingCoordinate: $this->resolveStartingCoordinate($streams),
            calories: $this->sumCalories($activityXml),
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
            streams: $this->activityStreamsMapper->fromStreamMap($streams, $activityId),
            laps: $activityLaps,
        );
    }

    /**
     * @return array{list<array<string, mixed>>, array<string, list<mixed>>, ?int}
     */
    private function parseLapsAndStreams(\SimpleXMLElement $activityXml): array
    {
        $startTimestamp = null;
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

        $hasNonZeroAltitude = $this->hasNonZeroAltitude($activityXml);

        $lapIndex = 0;
        foreach ($activityXml->Lap as $lap) {
            $lapAltitudes = [];
            $lapTimes = [];
            $lapDistances = [];

            // A lap can contain multiple <Track> elements (e.g. one per pause/resume).
            foreach ($lap->Track ?? [] as $track) {
                foreach ($track->Trackpoint ?? [] as $trackpoint) {
                    $rawTime = $this->stringChild($trackpoint, 'Time');
                    $time = null !== $rawTime ? SerializableDateTime::fromString($rawTime)->getTimestamp() : null;
                    $startTimestamp ??= $time;
                    $lapTimes[] = $time;

                    $altitude = $this->floatChild($trackpoint, 'AltitudeMeters');
                    // Suunto exports interleave spurious 0-altitudes between real
                    // values; treat exact zeros as missing, but only when the file
                    // has real altitude data (an all-zero file is kept as-is).
                    if ($hasNonZeroAltitude && 0.0 === $altitude) {
                        $altitude = null;
                    }
                    $lapAltitudes[] = $altitude;

                    $distance = $this->floatChild($trackpoint, 'DistanceMeters');
                    $lapDistances[] = $distance;

                    $streams[StreamType::TIME->value][] = (null !== $time && null !== $startTimestamp) ? $time - $startTimestamp : null;
                    $streams[StreamType::DISTANCE->value][] = $distance;
                    $streams[StreamType::ALTITUDE->value][] = $altitude;

                    $latitude = $this->floatChild($trackpoint->Position, 'LatitudeDegrees');
                    $longitude = $this->floatChild($trackpoint->Position, 'LongitudeDegrees');
                    // 0/0 ("null island") means no GPS fix (indoor rides, GPS not locked yet).
                    $streams[StreamType::LAT_LNG->value][] = (null !== $latitude && null !== $longitude && (0.0 !== $latitude || 0.0 !== $longitude)) ? [$latitude, $longitude] : null;

                    $streams[StreamType::HEART_RATE->value][] = $this->intChild($trackpoint->HeartRateBpm, 'Value');
                    $streams[StreamType::CADENCE->value][] = $this->intChild($trackpoint, 'Cadence');

                    $tpx = $this->extensionValues($trackpoint);
                    $streams[StreamType::VELOCITY->value][] = isset($tpx['Speed']) ? (float) $tpx['Speed'] : null;
                    $streams[StreamType::WATTS->value][] = isset($tpx['Watts']) ? (int) $tpx['Watts'] : null;
                    $streams[StreamType::TEMP->value][] = isset($tpx['Temperature']) ? (int) round((float) $tpx['Temperature']) : null;
                }
            }

            $laps[] = $this->buildLap(
                $lapIndex++,
                $lap,
                $this->elevationGain($lapAltitudes),
                $this->activeSeconds($lapTimes),
                $this->trackpointDistance($lapDistances),
            );
        }

        return [$laps, $streams, $startTimestamp];
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
     * @return array<string, mixed>
     */
    private function buildLap(int $index, \SimpleXMLElement $lap, float $elevationGain, int $activeSeconds, ?float $trackpointDistance): array
    {
        $totalTime = $this->floatChild($lap, 'TotalTimeSeconds');
        $totalTimeSeconds = null !== $totalTime ? (int) round($totalTime) : 0;

        // Elapsed time keeps the file's reported total (including pauses/gaps). Moving time is
        // capped at the active time so a recording gap (e.g. two merged rides) can never inflate it.
        $movingTime = null !== $totalTime ? min($totalTimeSeconds, $activeSeconds) : 0;

        // Prefer the recorded cumulative-distance stream; the lap summary field can be wrong in
        // merged files. Fall back to the summary when trackpoints carry no distance.
        $distance = $trackpointDistance ?? $this->floatChild($lap, 'DistanceMeters') ?? 0.0;

        return [
            'lap_index' => $index + 1,
            'name' => sprintf('Lap %d', $index + 1),
            'elapsed_time' => $totalTimeSeconds,
            'moving_time' => $movingTime,
            'distance' => $distance,
            'average_speed' => $movingTime > 0 ? $distance / $movingTime : 0.0,
            'max_speed' => $this->floatChild($lap, 'MaximumSpeed') ?? 0.0,
            'total_elevation_gain' => $elevationGain,
            'average_heartrate' => $this->intChild($lap->AverageHeartRateBpm, 'Value'),
        ];
    }

    /**
     * @param list<?int> $timestamps
     */
    private function activeSeconds(array $timestamps): int
    {
        $active = 0;
        $previous = null;
        foreach ($timestamps as $time) {
            if (null === $time) {
                continue;
            }
            if (null !== $previous) {
                $delta = $time - $previous;
                if ($delta > 0 && $delta <= self::MAX_RECORDING_GAP_IN_SECONDS) {
                    $active += $delta;
                }
            }
            $previous = $time;
        }

        return $active;
    }

    /**
     * @param list<?float> $distances
     */
    private function trackpointDistance(array $distances): ?float
    {
        $values = array_values(array_filter($distances, static fn (?float $distance): bool => null !== $distance));
        if ([] === $values) {
            return null;
        }

        return $values[count($values) - 1] - $values[0];
    }

    /**
     * @param array<string, list<mixed>> $streams
     */
    private function resolveStartingCoordinate(array $streams): ?Coordinate
    {
        foreach ($streams[StreamType::LAT_LNG->value] ?? [] as $point) {
            if (is_array($point)) {
                return Coordinate::createFromLatAndLng(
                    Latitude::fromString((string) $point[0]),
                    Longitude::fromString((string) $point[1]),
                );
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function extensionValues(\SimpleXMLElement $trackpoint): array
    {
        $values = [];
        if (!property_exists($trackpoint->Extensions, 'TPX') || null === $trackpoint->Extensions->TPX) {
            return $values;
        }

        foreach ($trackpoint->Extensions->TPX->children() as $name => $value) {
            $values[$name] = (string) $value;
        }

        return $values;
    }

    private function hasNonZeroAltitude(\SimpleXMLElement $activityXml): bool
    {
        foreach ($activityXml->Lap as $lap) {
            foreach ($lap->Track ?? [] as $track) {
                foreach ($track->Trackpoint ?? [] as $trackpoint) {
                    $altitude = $this->floatChild($trackpoint, 'AltitudeMeters');
                    if (null !== $altitude && 0.0 !== $altitude) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function sumCalories(\SimpleXMLElement $activity): ?int
    {
        $calories = 0;
        $found = false;
        foreach ($activity->Lap as $lap) {
            if (null !== ($lapCalories = $this->intChild($lap, 'Calories'))) {
                $calories += $lapCalories;
                $found = true;
            }
        }

        return $found ? $calories : null;
    }

    private function stringChild(\SimpleXMLElement $parent, string $child): ?string
    {
        return property_exists($parent, $child) && null !== $parent->{$child} ? (string) $parent->{$child} : null;
    }

    private function floatChild(\SimpleXMLElement $parent, string $child): ?float
    {
        return null !== ($value = $this->stringChild($parent, $child)) ? (float) $value : null;
    }

    private function intChild(\SimpleXMLElement $parent, string $child): ?int
    {
        return null !== ($value = $this->stringChild($parent, $child)) ? (int) $value : null;
    }

    /**
     * @param list<?float> $distances
     * @param list<?int>   $times
     *
     * @return list<?float>
     */
    private function deriveVelocityStream(array $distances, array $times): array
    {
        $velocities = [];
        $previousDistance = null;
        $previousTime = null;

        foreach ($distances as $index => $distance) {
            $time = $times[$index] ?? null;
            if (null === $distance || null === $time) {
                $velocities[] = null;
                continue;
            }

            if (null !== $previousDistance && null !== $previousTime && $time > $previousTime) {
                $velocities[] = ($distance - $previousDistance) / ($time - $previousTime);
            } else {
                $velocities[] = null;
            }

            $previousDistance = $distance;
            $previousTime = $time;
        }

        return $velocities;
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
