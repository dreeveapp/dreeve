<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Cache\Tag\ScopedCacheTag;

final readonly class ActivityCollectionCacheTag
{
    public static function for(ActivityCollectionId $activityCollectionId): ScopedCacheTag
    {
        return ScopedCacheTag::for(RootCacheTag::ACTIVITY_COLLECTIONS, $activityCollectionId->toUnprefixedString());
    }
}
