<?php

declare(strict_types=1);

namespace App\Domain\Backup;

final readonly class Backup
{
    private function __construct(
        private string $filename,
        private int $sizeInBytes,
    ) {
    }

    public static function create(
        string $filename,
        int $sizeInBytes,
    ): self {
        return new self(
            filename: $filename,
            sizeInBytes: $sizeInBytes,
        );
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function getSizeInMegaBytes(): float
    {
        return $this->sizeInBytes / 1024 / 1024;
    }
}
