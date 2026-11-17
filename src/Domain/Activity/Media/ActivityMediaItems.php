<?php

declare(strict_types=1);

namespace App\Domain\Activity\Media;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityMediaItem>
 */
final class ActivityMediaItems extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityMediaItem::class;
    }
}
