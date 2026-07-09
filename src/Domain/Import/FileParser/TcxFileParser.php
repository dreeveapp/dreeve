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
        private Clock $clock,
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

        $tcxSport = (string) $activityXml['Sport'];
        $sportType = match (strtolower($tcxSport)) {
            'running' => SportType::RUN,
            'biking', 'cycling' => SportType::RIDE,
            'walking' => SportType::WALK,
            'hiking' => SportType::HIKE,
            'swimming' => SportType::SWIM,
            default => SportType::tryFrom($tcxSport) ?? SportType::WORKOUT,
        };
        $deviceName = property_exists($activityXml->Creator, 'Name') && null !== $activityXml->Creator->Name ? (string) $activityXml->Creator->Name : null;

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
        ];
        $laps = [];

        foreach ($activityXml->Lap as $lapIndex => $lap) {
            $lapStart = isset($lap['StartTime']) ? SerializableDateTime::fromString((string) $lap['StartTime'])->getTimestamp() : null;
            $lapAltitudes = [];
            $lapTimes = [];
            $lapDistances = [];

            foreach ($lap->Track->Trackpoint ?? [] as $trackpoint) {
                $time = property_exists($trackpoint, 'Time') && null !== $trackpoint->Time ? SerializableDateTime::fromString((string) $trackpoint->Time)->getTimestamp() : null;
                $startTimestamp ??= $time;
                $lapTimes[] = $time;

                $altitude = property_exists($trackpoint, 'AltitudeMeters') && null !== $trackpoint->AltitudeMeters ? (float) $trackpoint->AltitudeMeters : null;
                $lapAltitudes[] = $altitude;

                $distance = property_exists($trackpoint, 'DistanceMeters') && null !== $trackpoint->DistanceMeters ? (float) $trackpoint->DistanceMeters : null;
                $lapDistances[] = $distance;

                $streams[StreamType::TIME->value][] = (null !== $time && null !== $startTimestamp) ? $time - $startTimestamp : null;
                $streams[StreamType::DISTANCE->value][] = $distance;
                $streams[StreamType::ALTITUDE->value][] = $altitude;

                $latitude = property_exists($trackpoint->Position, 'LatitudeDegrees') && null !== $trackpoint->Position->LatitudeDegrees ? (float) $trackpoint->Position->LatitudeDegrees : null;
                $longitude = property_exists($trackpoint->Position, 'LongitudeDegrees') && null !== $trackpoint->Position->LongitudeDegrees ? (float) $trackpoint->Position->LongitudeDegrees : null;
                $streams[StreamType::LAT_LNG->value][] = (null !== $latitude && null !== $longitude) ? [$latitude, $longitude] : null;

                $streams[StreamType::HEART_RATE->value][] = property_exists($trackpoint->HeartRateBpm, 'Value') && null !== $trackpoint->HeartRateBpm->Value ? (int) $trackpoint->HeartRateBpm->Value : null;
                $streams[StreamType::CADENCE->value][] = property_exists($trackpoint, 'Cadence') && null !== $trackpoint->Cadence ? (int) $trackpoint->Cadence : null;

                $tpx = $this->extensionValues($trackpoint);
                $streams[StreamType::VELOCITY->value][] = isset($tpx['Speed']) ? (float) $tpx['Speed'] : null;
                $streams[StreamType::WATTS->value][] = isset($tpx['Watts']) ? (int) $tpx['Watts'] : null;
            }

            $laps[] = $this->buildLap(
                (int) $lapIndex,
                $lap,
                $lapStart,
                $this->elevationGain($lapAltitudes),
                $this->activeSeconds($lapTimes),
                $this->trackpointDistance($lapDistances),
            );
        }

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
     * @return array<string, mixed>
     */
    private function buildLap(int $index, \SimpleXMLElement $lap, ?int $lapStart, float $elevationGain, int $activeSeconds, ?float $trackpointDistance): array
    {
        $hasTotalTime = property_exists($lap, 'TotalTimeSeconds') && null !== $lap->TotalTimeSeconds;
        $totalTimeSeconds = $hasTotalTime ? (int) round((float) $lap->TotalTimeSeconds) : 0;

        // Elapsed time keeps the file's reported total (including pauses/gaps). Moving time is
        // capped at the active time so a recording gap (e.g. two merged rides) can never inflate it.
        $movingTime = $hasTotalTime ? min($totalTimeSeconds, $activeSeconds) : 0;

        // Prefer the recorded cumulative-distance stream; the lap summary field can be wrong in
        // merged files. Fall back to the summary when trackpoints carry no distance.
        $distance = $trackpointDistance ?? (property_exists($lap, 'DistanceMeters') && null !== $lap->DistanceMeters ? (float) $lap->DistanceMeters : 0.0);

        return [
            'id' => $index + 1,
            'lap_index' => $index + 1,
            'name' => sprintf('Lap %d', $index + 1),
            'elapsed_time' => $totalTimeSeconds,
            'moving_time' => $movingTime,
            'distance' => $distance,
            'average_speed' => $movingTime > 0 ? $distance / $movingTime : 0.0,
            'max_speed' => property_exists($lap, 'MaximumSpeed') && null !== $lap->MaximumSpeed ? (float) $lap->MaximumSpeed : 0.0,
            'total_elevation_gain' => $elevationGain,
            'average_heartrate' => property_exists($lap->AverageHeartRateBpm, 'Value') && null !== $lap->AverageHeartRateBpm->Value ? (int) $lap->AverageHeartRateBpm->Value : null,
            'start_date' => null !== $lapStart ? SerializableDateTime::fromTimestamp($lapStart)->format(\DateTimeInterface::ATOM) : null,
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

    private function sumCalories(\SimpleXMLElement $activity): ?int
    {
        $calories = 0;
        $found = false;
        foreach ($activity->Lap as $lap) {
            if (property_exists($lap, 'Calories') && null !== $lap->Calories) {
                $calories += (int) $lap->Calories;
                $found = true;
            }
        }

        return $found ? $calories : null;
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
