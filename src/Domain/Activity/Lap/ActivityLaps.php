<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityLap>
 */
final class ActivityLaps extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityLap::class;
    }
}
