<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingGoals\FindTrainingGoalMetrics;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindTrainingGoalMetricsResponse implements Response
{
    public function __construct(
        private Kilometer $distance,
        private Meter $elevation,
        private Seconds $movingTime,
        private SimpleUnit $numberOfActivities,
        private SimpleUnit $calories,
    ) {
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    public function getElevation(): Meter
    {
        return $this->elevation;
    }

    public function getMovingTime(): Seconds
    {
        return $this->movingTime;
    }

    public function getNumberOfActivities(): SimpleUnit
    {
        return $this->numberOfActivities;
    }

    public function getCalories(): SimpleUnit
    {
        return $this->calories;
    }
}
