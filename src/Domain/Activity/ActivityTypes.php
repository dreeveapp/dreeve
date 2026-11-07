<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<ActivityType>
 */
final class ActivityTypes extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return ActivityType::class;
    }
}
