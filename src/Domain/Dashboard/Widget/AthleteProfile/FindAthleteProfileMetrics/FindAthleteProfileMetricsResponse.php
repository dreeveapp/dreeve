<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\AthleteProfile\FindAthleteProfileMetrics;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Time\Hour;

final readonly class FindAthleteProfileMetricsResponse implements Response
{
    /**
     * @param int[] $activityMovingTimesInSeconds
     */
    public function __construct(
        private ActivityIds $activityIds,
        private Hour $movingTime,
        private int $numberOfActivities,
        private int $numberOfActiveDays,
        private int $numberOfActivitiesInMostPopularActivityType,
        private array $activityMovingTimesInSeconds,
    ) {
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    public function getMovingTime(): Hour
    {
        return $this->movingTime;
    }

    public function getNumberOfActivities(): int
    {
        return $this->numberOfActivities;
    }

    public function getNumberOfActiveDays(): int
    {
        return $this->numberOfActiveDays;
    }

    public function getNumberOfActivitiesInMostPopularActivityType(): int
    {
        return $this->numberOfActivitiesInMostPopularActivityType;
    }

    /**
     * @return int[]
     */
    public function getActivityMovingTimesInSeconds(): array
    {
        return $this->activityMovingTimesInSeconds;
    }
}
