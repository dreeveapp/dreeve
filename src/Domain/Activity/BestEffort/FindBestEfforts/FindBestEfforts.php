<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort\FindBestEfforts;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;

/**
 * @implements Query<\App\Domain\Activity\BestEffort\FindBestEfforts\FindBestEffortsResponse>
 */
final readonly class FindBestEfforts implements Query
{
    public function __construct(
        private ?ActivityType $activityType = null,
        private ?ConvertableToMeter $distance = null,
    ) {
    }

    public function getActivityType(): ?ActivityType
    {
        return $this->activityType;
    }

    public function getDistance(): ?ConvertableToMeter
    {
        return $this->distance;
    }
}
