<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityLapPower>
 */
final class ActivityLapPowers extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityLapPower::class;
    }

    public function forLap(ActivityLapId $lapId): ?ActivityLapPower
    {
        return $this->find(
            static fn (ActivityLapPower $power): bool => $power->getLapId()->toUnprefixedString() === $lapId->toUnprefixedString()
        );
    }
}
