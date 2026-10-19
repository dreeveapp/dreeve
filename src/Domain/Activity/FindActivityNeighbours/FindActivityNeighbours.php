<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityNeighbours;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\FindActivityNeighbours\FindActivityNeighboursResponse>
 */
final readonly class FindActivityNeighbours implements Query
{
    public function __construct(
        private ActivityId $activityId,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }
}
