<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Cache\Tag\ScopedCacheTag;

final readonly class MediaItemCacheTag
{
    public static function for(MediaItemId $mediaItemId): ScopedCacheTag
    {
        return ScopedCacheTag::for(RootCacheTag::MEDIA_ITEMS, $mediaItemId->toUnprefixedString());
    }

    /**
     * @return list<ScopedCacheTag>
     */
    public static function forAll(MediaItemIds $mediaItemIds): array
    {
        return array_values($mediaItemIds->map(self::for(...)));
    }
}
