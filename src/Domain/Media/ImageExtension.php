<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum ImageExtension: string
{
    case JPG = 'jpg';
    case JPEG = 'jpeg';
    case PNG = 'png';
    case WEBP = 'webp';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $extension): string => $extension->value, self::cases());
    }

    public static function isSupported(string $extension): bool
    {
        return null !== self::tryFrom(strtolower($extension));
    }
}
