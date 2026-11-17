<?php

declare(strict_types=1);

namespace App\Domain\Image;

final readonly class RemovedImage
{
    public function __construct(
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
