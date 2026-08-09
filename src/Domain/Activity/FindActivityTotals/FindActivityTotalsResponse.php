<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityTotals;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FindActivityTotalsResponse implements Response
{
    public function __construct(
        private int $totalActivities,
        private Kilometer $totalDistance,
        private Meter $totalElevation,
        private int $totalCalories,
        private int $totalMovingTimeInSeconds,
        private int $totalDaysOfWorkingOut,
        private ?SerializableDateTime $firstActivityStartDate,
    ) {
    }

    public function getTotalActivities(): int
    {
        return $this->totalActivities;
    }

    public function getTotalDistance(): Kilometer
    {
        return $this->totalDistance;
    }

    public function getTotalElevation(): Meter
    {
        return $this->totalElevation;
    }

    public function getTotalCalories(): int
    {
        return $this->totalCalories;
    }

    public function getTotalMovingTimeInSeconds(): int
    {
        return $this->totalMovingTimeInSeconds;
    }

    public function getTotalDaysOfWorkingOut(): int
    {
        return $this->totalDaysOfWorkingOut;
    }

    public function getFirstActivityStartDate(): ?SerializableDateTime
    {
        return $this->firstActivityStartDate;
    }
}
