<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum ImageOrientation
{
    case PORTRAIT;
    case LANDSCAPE;

    public static function fromWidthAndHeight(int $width, int $height): self
    {
        return match (true) {
            $width > $height => self::LANDSCAPE,
            default => self::PORTRAIT,
        };
    }
}
