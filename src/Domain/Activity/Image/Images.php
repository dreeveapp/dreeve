<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<Image>
 */
final class Images extends Collection
{
    public function getItemClassName(): string
    {
        return Image::class;
    }
}
