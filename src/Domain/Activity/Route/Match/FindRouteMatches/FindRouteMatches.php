<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match\FindRouteMatches;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\Route\Match\FindRouteMatches\FindRouteMatchesResponse>
 */
final readonly class FindRouteMatches implements Query
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
