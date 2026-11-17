<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class MediaItemId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'mediaItem-';
    }
}
