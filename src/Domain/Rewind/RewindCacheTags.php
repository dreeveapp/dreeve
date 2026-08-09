<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\Year;

final readonly class RewindCacheTags
{
    public static function forOption(string $rewindOption): CacheTags
    {
        if (FindAvailableRewindOptions::ALL_TIME === $rewindOption) {
            return CacheTags::of(
                RootCacheTag::ACTIVITIES,
                RootCacheTag::ACTIVITY_IMAGES,
                RootCacheTag::GEAR,
            );
        }

        $year = Year::fromInt((int) $rewindOption);

        return CacheTags::of(
            RootCacheTag::ACTIVITIES->forYear($year),
            RootCacheTag::ACTIVITY_IMAGES->forYear($year),
            RootCacheTag::GEAR,
        );
    }
}
