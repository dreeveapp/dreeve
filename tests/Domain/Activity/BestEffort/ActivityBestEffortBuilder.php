<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;

final class ActivityBestEffortBuilder
{
    private ActivityId $activityId;
    private SportType $sportType = SportType::RIDE;
    private Meter $distanceInMeter;
    private int $timeInSeconds = 3600;

    public function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('test');
        $this->distanceInMeter = Meter::from(10000);
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityBestEffort
    {
        return ActivityBestEffort::fromState(
            activityId: $this->activityId,
            distanceInMeter: $this->distanceInMeter,
            sportType: $this->sportType,
            timeInSeconds: $this->timeInSeconds
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withSportType(SportType $sportType): self
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function withDistanceInMeter(Meter $distanceInMeter): self
    {
        $this->distanceInMeter = $distanceInMeter;

        return $this;
    }

    public function withTimeInSeconds(int $timeInSeconds): self
    {
        $this->timeInSeconds = $timeInSeconds;

        return $this;
    }
}
