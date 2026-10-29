<?php

declare(strict_types=1);

namespace App\Application\OpenGraph;

use App\Infrastructure\ValueObject\String\Name;

final readonly class OpenGraph
{
    public function __construct(
        private ?string $path,
        private string $title,
        private ?string $description,
        private ?string $imagePath = null,
    ) {
    }

    public static function default(Name $athleteName): self
    {
        return new self(
            path: null,
            title: (string) $athleteName,
            description: null,
        );
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }
}
