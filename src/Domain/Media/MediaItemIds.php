<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<MediaItemId>
 */
final class MediaItemIds extends Collection
{
    public function getItemClassName(): string
    {
        return MediaItemId::class;
    }
}
