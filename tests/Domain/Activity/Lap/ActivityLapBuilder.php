<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Lap;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Lap\ActivityLapId;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;

final class ActivityLapBuilder
{
    private ActivityLapId $lapId;
    private ActivityId $activityId;
    private int $lapNumber = 1;
    private readonly string $name;
    private readonly int $elapsedTimeInSeconds;
    private readonly int $movingTimeInSeconds;
    private readonly Meter $distance;
    private MetersPerSecond $averageSpeed;
    private readonly MetersPerSecond $minAverageSpeed;
    private readonly MetersPerSecond $maxAverageSpeed;
    private MetersPerSecond $maxSpeed;
    private readonly Meter $elevationDifference;
    private readonly ?int $averageHeartRate;

    private function __construct()
    {
        $this->lapId = ActivityLapId::fromUnprefixed('test');
        $this->activityId = ActivityId::fromUnprefixed('test');
        $this->name = 'test';
        $this->distance = Meter::from(100);
        $this->elapsedTimeInSeconds = 120;
        $this->movingTimeInSeconds = 110;
        $this->elevationDifference = Meter::from(2);
        $this->averageSpeed = MetersPerSecond::from(3);
        $this->minAverageSpeed = MetersPerSecond::from(1);
        $this->maxAverageSpeed = MetersPerSecond::from(8);
        $this->maxSpeed = MetersPerSecond::from(8);
        $this->averageHeartRate = null;
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityLap
    {
        return ActivityLap::fromState(
            lapId: $this->lapId,
            activityId: $this->activityId,
            lapNumber: $this->lapNumber,
            name: $this->name,
            elapsedTimeInSeconds: $this->elapsedTimeInSeconds,
            movingTimeInSeconds: $this->movingTimeInSeconds,
            distance: $this->distance,
            averageSpeed: $this->averageSpeed,
            minAverageSpeed: $this->minAverageSpeed,
            maxAverageSpeed: $this->maxAverageSpeed,
            maxSpeed: $this->maxSpeed,
            elevationDifference: $this->elevationDifference,
            averageHeartRate: $this->averageHeartRate,
        );
    }

    public function withLapId(ActivityLapId $lapId): self
    {
        $this->lapId = $lapId;

        return $this;
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withLapNumber(int $labNumber): self
    {
        $this->lapNumber = $labNumber;

        return $this;
    }

    public function withAverageSpeed(MetersPerSecond $averageSpeed): self
    {
        $this->averageSpeed = $averageSpeed;

        return $this;
    }

    public function withMaxSpeed(MetersPerSecond $maxSpeed): self
    {
        $this->maxSpeed = $maxSpeed;

        return $this;
    }
}
