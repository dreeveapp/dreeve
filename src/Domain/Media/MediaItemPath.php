<?php

declare(strict_types=1);

namespace App\Domain\Media;

final readonly class MediaItemPath
{
    private function __construct(
        private string $localPath,
    ) {
    }

    public static function fromLocalPath(string $localPath): self
    {
        return new self(ltrim($localPath, '/'));
    }

    public static function fromFileSystemPath(string $fileSystemPath): self
    {
        return new self('files/'.ltrim($fileSystemPath, '/'));
    }

    public function toLocalPath(): string
    {
        return $this->localPath;
    }

    public function toFileSystemPath(): string
    {
        return (string) preg_replace('#^files/#', '', $this->localPath);
    }
}
