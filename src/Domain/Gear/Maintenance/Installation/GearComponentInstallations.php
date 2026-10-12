<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearIds;
use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

/**
 * @extends Collection<GearComponentInstallation>
 */
final class GearComponentInstallations extends Collection
{
    public function getItemClassName(): string
    {
        return GearComponentInstallation::class;
    }

    public function getEarliestFrom(): ?SerializableDateTime
    {
        $earliestFrom = null;
        foreach ($this as $installation) {
            if (!$from = $installation->getFrom()) {
                return null;
            }
            if (!$earliestFrom || $from->isBefore($earliestFrom)) {
                $earliestFrom = $from;
            }
        }

        return $earliestFrom;
    }

    public function getGearIds(): GearIds
    {
        return GearIds::fromArray(array_values(array_filter($this->map(
            static fn (GearComponentInstallation $installation) => $installation->getParent()->getGearId(),
        ))))->unique();
    }
}
