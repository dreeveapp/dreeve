<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityCollection>
 */
final class ActivityCollections extends Collection
{
    public function getActivityIds(): ActivityIds
    {
        return ActivityIds::fromArray(array_merge(...$this->map(
            fn (ActivityCollection $activityCollection): array => $activityCollection->getActivityIds()->toArray()
        )))->unique();
    }

    public function getItemClassName(): string
    {
        return ActivityCollection::class;
    }
}
