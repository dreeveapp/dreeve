<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<\App\Domain\Gear\Maintenance\GearComponent>
 */
final class GearComponents extends Collection
{
    public function getItemClassName(): string
    {
        return GearComponent::class;
    }

    public function getAllReferencedGearIds(): GearIds
    {
        $gearIds = GearIds::empty();
        foreach ($this as $gearComponent) {
            foreach ($gearComponent->getAttachedTo() as $gearId) {
                $gearIds->add($gearId);
            }
        }

        return $gearIds->unique();
    }

    public function resolveUsageWindowsFor(GearComponent $gearComponent): UsageWindows
    {
        return UsageWindows::fromArray($gearComponent->getInstallations()->map(
            static fn (GearComponentInstallation $installation): UsageWindow => UsageWindow::create(
                gearId: $installation->getGearId(),
                from: $installation->getFrom(),
                until: $installation->getUntil(),
            ),
        ));
    }
}
