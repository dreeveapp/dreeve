<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityCollection>
 */
final class ActivityCollections extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityCollection::class;
    }
}
