<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearIds;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<GearComponentInstallation>
 */
final class GearComponentInstallations extends Collection
{
    public function getItemClassName(): string
    {
        return GearComponentInstallation::class;
    }

    public function getGearIds(): GearIds
    {
        return GearIds::fromArray(array_values(array_filter($this->map(
            static fn (GearComponentInstallation $installation) => $installation->getParent()->getGearId(),
        ))))->unique();
    }
}
