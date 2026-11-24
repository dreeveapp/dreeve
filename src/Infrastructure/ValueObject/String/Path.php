<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

use App\Domain\Media\ImageExtension;

final readonly class Path extends NonEmptyStringLiteral
{
    public function getExtension(): string
    {
        $extension = pathinfo((string) $this, PATHINFO_EXTENSION);

        return strtolower($extension);
    }

    public function getFilename(): string
    {
        $extension = $this->getExtension();
        $filename = $this->getFilenameWithoutExtension();

        return '' === $extension ? $filename : sprintf('%s.%s', $filename, $extension);
    }

    public function getFilenameWithoutExtension(): string
    {
        return pathinfo((string) $this, PATHINFO_FILENAME);
    }

    public function getBasename(): string
    {
        return pathinfo((string) $this, PATHINFO_BASENAME);
    }

    public function containsTraversal(): bool
    {
        return str_contains((string) $this, '..');
    }

    public function isSupportedImage(): bool
    {
        return ImageExtension::isSupported($this->getExtension());
    }
}
