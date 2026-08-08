<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\ValueObject\Time\Year;

final readonly class RewindCacheTags
{
    public static function forOption(string $rewindOption): CacheTags
    {
        if (FindAvailableRewindOptions::ALL_TIME === $rewindOption) {
            return CacheTags::of(
                CacheTag::ACTIVITIES,
                CacheTag::ACTIVITY_IMAGES,
                CacheTag::GEAR,
            );
        }

        $year = Year::fromInt((int) $rewindOption);

        return CacheTags::of(
            CacheTag::ACTIVITIES->forYear($year),
            CacheTag::ACTIVITY_IMAGES->forYear($year),
            CacheTag::GEAR,
        );
    }
}
