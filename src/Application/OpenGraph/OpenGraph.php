<?php

declare(strict_types=1);

namespace App\Application\OpenGraph;

final readonly class OpenGraph
{
    public function __construct(
        private string $path,
        private string $title,
        private string $description,
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
}
