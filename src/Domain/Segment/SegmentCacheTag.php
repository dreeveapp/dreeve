<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Cache\Tag\ScopedCacheTag;

final readonly class SegmentCacheTag
{
    public static function for(SegmentId $segmentId): ScopedCacheTag
    {
        return ScopedCacheTag::for(RootCacheTag::SEGMENTS, $segmentId->toUnprefixedString());
    }
}
