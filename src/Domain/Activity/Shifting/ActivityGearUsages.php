<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityGearUsage>
 */
final class ActivityGearUsages extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityGearUsage::class;
    }

    public function filterOnPosition(GearPosition $position): self
    {
        return $this->filter(fn (ActivityGearUsage $gearUsage): bool => $position === $gearUsage->getPosition());
    }
}
