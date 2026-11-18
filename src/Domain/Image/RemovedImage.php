<?php

declare(strict_types=1);

namespace App\Domain\Image;

use App\Domain\Media\MediaItemId;

final readonly class RemovedImage
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
