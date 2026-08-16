<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Lap\ActivityLapIdFactory;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;

final readonly class ActivityLapsMapper
{
    public function __construct(
        private ActivityLapIdFactory $activityLapIdFactory,
    ) {
    }

    /**
     * @param list<ParsedActivityLap> $parsedLaps
     */
    public function map(array $parsedLaps, ActivityId $activityId): ActivityLaps
    {
        $averageSpeeds = array_map(
            static fn (ParsedActivityLap $lap): float => $lap->getAverageSpeed()->toFloat(),
            $parsedLaps
        );
        $minAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? min($averageSpeeds) : 0.0);
        $maxAverageSpeed = MetersPerSecond::from([] !== $averageSpeeds ? max($averageSpeeds) : 0.0);

        $laps = ActivityLaps::empty();
        foreach ($parsedLaps as $parsedLap) {
            $laps->add(ActivityLap::create(
                lapId: $this->activityLapIdFactory->random(),
                activityId: $activityId,
                lapNumber: $parsedLap->getLapNumber(),
                name: $parsedLap->getName(),
                elapsedTimeInSeconds: $parsedLap->getElapsedTimeInSeconds(),
                movingTimeInSeconds: $parsedLap->getMovingTimeInSeconds(),
                distance: $parsedLap->getDistance(),
                averageSpeed: $parsedLap->getAverageSpeed(),
                minAverageSpeed: $minAverageSpeed,
                maxAverageSpeed: $maxAverageSpeed,
                maxSpeed: $parsedLap->getMaxSpeed(),
                elevationDifference: $parsedLap->getElevationDifference(),
                averageHeartRate: $parsedLap->getAverageHeartRate(),
            ));
        }

        return $laps;
    }
}
