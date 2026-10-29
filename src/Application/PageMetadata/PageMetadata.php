<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

final readonly class PageMetadata
{
    public function __construct(
        private string $path,
        private string $title,
        private string $description,
        private ?string $imagePath = null,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }
}
