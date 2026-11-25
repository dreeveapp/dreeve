<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindTotalsForActivitiesWithoutGear;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindTotalsForActivitiesWithoutGearResponse implements Response
{
    public function __construct(
        private int $numberOfActivities,
        private Meter $distance,
        private Seconds $movingTime,
        private Meter $elevation,
        private int $calories,
    ) {
    }

    public function getNumberOfActivities(): int
    {
        return $this->numberOfActivities;
    }

    public function getDistance(): Meter
    {
        return $this->distance;
    }

    public function getMovingTime(): Seconds
    {
        return $this->movingTime;
    }

    public function getElevation(): Meter
    {
        return $this->elevation;
    }

    public function getCalories(): int
    {
        return $this->calories;
    }
}
