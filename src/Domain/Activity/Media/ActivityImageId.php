<?php

declare(strict_types=1);

namespace App\Domain\Activity\Media;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class ActivityImageId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'activityImage-';
    }
}
