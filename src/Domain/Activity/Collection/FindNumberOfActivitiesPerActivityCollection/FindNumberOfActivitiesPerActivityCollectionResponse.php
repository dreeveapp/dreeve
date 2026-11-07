<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindNumberOfActivitiesPerActivityCollectionResponse implements Response
{
    public function __construct(
        /** @var array<string, int> */
        private array $numberOfActivitiesPerActivityCollectionId,
    ) {
    }

    public function getNumberOfActivitiesFor(ActivityCollectionId $activityCollectionId): int
    {
        return $this->numberOfActivitiesPerActivityCollectionId[(string) $activityCollectionId] ?? 0;
    }
}
