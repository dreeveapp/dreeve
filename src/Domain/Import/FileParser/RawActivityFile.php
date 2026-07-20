<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Infrastructure\ValueObject\String\Path;

final readonly class RawActivityFile
{
    private function __construct(
        private Path $filePath,
        private string $fileContents,
    ) {
    }

    public static function from(
        Path $filePath,
        string $fileContents,
    ): self {
        return new self(
            filePath: $filePath,
            fileContents: $fileContents,
        );
    }

    public function getPath(): Path
    {
        return $this->filePath;
    }

    public function getContents(): string
    {
        return $this->fileContents;
    }
}
