<?php

declare(strict_types=1);

namespace App\Domain\Media;

final readonly class MediaItemPath
{
    private function __construct(
        private string $path,
    ) {
    }

    public static function fromString(string $path): self
    {
        return new self($path);
    }

    public function toString(): string
    {
        return $this->path;
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function toUrlPath(): string
    {
        return '/files/'.$this->path;
    }
}
