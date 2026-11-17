<?php

declare(strict_types=1);

namespace App\Domain\Media;

interface MediaItemRepository
{
    public function add(MediaItem $mediaItem): void;

    public function update(MediaItem $mediaItem): void;

    public function delete(MediaItem $mediaItem): void;

    public function find(MediaItemId $mediaItemId): MediaItem;

    public function findByIds(MediaItemIds $mediaItemIds): MediaItems;
}
