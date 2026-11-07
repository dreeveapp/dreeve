<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\EnumCollection;

/**
 * @extends EnumCollection<ActivityType>
 */
final class ActivityTypes extends EnumCollection
{
    public function getItemClassName(): string
    {
        return ActivityType::class;
    }
}
