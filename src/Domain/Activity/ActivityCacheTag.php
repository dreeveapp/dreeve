<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\ScopedCacheTag;

final readonly class ActivityCacheTag
{
    public static function for(ActivityId $activityId): ScopedCacheTag
    {
        return ScopedCacheTag::for(CacheTag::ACTIVITIES, $activityId->toUnprefixedString());
    }
}
