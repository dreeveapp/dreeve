<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Backup
{
    private function __construct(
        private string $filename,
        private int $sizeInBytes,
        private ?BackupManifest $manifest,
    ) {
    }

    public static function create(
        string $filename,
        int $sizeInBytes,
        ?BackupManifest $manifest,
    ): self {
        return new self(
            filename: $filename,
            sizeInBytes: $sizeInBytes,
            manifest: $manifest,
        );
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return SerializableDateTime::createFromFormat('Y-m-d-His', substr($this->filename, 7, 17));
    }

    public function getSizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function getSizeInMegaBytes(): float
    {
        return $this->sizeInBytes / 1024 / 1024;
    }

    public function getManifest(): ?BackupManifest
    {
        return $this->manifest;
    }
}
