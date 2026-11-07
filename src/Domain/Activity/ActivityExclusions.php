<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<ActivityExclusion>
 */
final class ActivityExclusions extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return ActivityExclusion::class;
    }
}
