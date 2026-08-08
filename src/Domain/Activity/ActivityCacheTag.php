<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\Cache\ScopedCacheTag;

final readonly class ActivityCacheTag
{
    public static function for(ActivityId $activityId): ScopedCacheTag
    {
        return ScopedCacheTag::for(RootCacheTag::ACTIVITIES, $activityId->toUnprefixedString());
    }
}
