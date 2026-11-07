<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\EnumCollection;

/**
 * @extends EnumCollection<ActivityExclusion>
 */
final class ActivityExclusions extends EnumCollection
{
    public function getItemClassName(): string
    {
        return ActivityExclusion::class;
    }
}
