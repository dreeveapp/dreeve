<?php

declare(strict_types=1);

namespace App\Domain\Media\DropZone;

use App\Domain\Media\MediaItemId;

final readonly class RemovedMediaItem
{
    public function __construct(
        private MediaItemId $id,
    ) {
    }

    public function getId(): MediaItemId
    {
        return $this->id;
    }
}
