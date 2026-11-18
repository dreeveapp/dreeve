<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<MediaItem>
 */
final class MediaItems extends Collection
{
    public function getItemClassName(): string
    {
        return MediaItem::class;
    }

    /**
     * @return array<string, MediaItem>
     */
    public function keyById(): array
    {
        $mediaItems = [];
        foreach ($this as $mediaItem) {
            $mediaItems[(string) $mediaItem->getId()] = $mediaItem;
        }

        return $mediaItems;
    }
}
