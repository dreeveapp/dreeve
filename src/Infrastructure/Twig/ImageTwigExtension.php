<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Media\ImageExtension;
use Twig\Attribute\AsTwigFunction;

final readonly class ImageTwigExtension
{
    /**
     * @return array<string>
     */
    #[AsTwigFunction('supportedImageExtensions')]
    public static function supportedImageExtensions(): array
    {
        return ImageExtension::values();
    }
}
