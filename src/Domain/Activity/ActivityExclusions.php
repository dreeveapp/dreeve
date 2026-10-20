<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityExclusion>
 */
final class ActivityExclusions extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityExclusion::class;
    }
}
