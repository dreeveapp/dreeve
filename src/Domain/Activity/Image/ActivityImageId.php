<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Domain\Image\ImagePath;
use App\Infrastructure\ValueObject\Identifier\Identifier;
use App\Infrastructure\ValueObject\String\Path;

final readonly class ActivityImageId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'activityImage-';
    }

    public static function fromImagePath(ImagePath $path): self
    {
        return self::fromUnprefixed(Path::fromString($path->toLocalImagePath())->getFilenameWithoutExtension());
    }
}
