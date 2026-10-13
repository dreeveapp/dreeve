<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallationLoopDetected;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

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
            foreach ($gearComponent->getInstallations()->getGearIds() as $gearId) {
                $gearIds->add($gearId);
            }
        }

        return $gearIds->unique();
    }

    public function withoutRetired(): GearComponents
    {
        return $this->filter(static fn (GearComponent $gearComponent): bool => !$gearComponent->isRetired());
    }

    public function getGearIdsCurrentlyUsing(GearComponent $gearComponent, SerializableDateTime $now): GearIds
    {
        return $this->resolveUsageWindowsFor($gearComponent)->getGearIdsActiveOn($now);
    }

    public function getParentComponentsOf(GearComponent $gearComponent, SerializableDateTime $on): GearComponents
    {
        $parentComponents = GearComponents::empty();
        foreach ($gearComponent->getInstallations() as $installation) {
            if (!$installation->isActiveOn($on)) {
                continue;
            }
            if (!($parentComponentId = $installation->getParent()->getGearComponentId()) instanceof GearComponentId) {
                continue;
            }
            if (($parentComponent = $this->findById($parentComponentId)) instanceof GearComponent) {
                $parentComponents->add($parentComponent);
            }
        }

        return $parentComponents;
    }

    public function hasComponentsInstalledOn(GearComponent $gearComponent): bool
    {
        foreach ($this as $candidate) {
            foreach ($candidate->getInstallations() as $installation) {
                if ((string) $installation->getParent()->getGearComponentId() === (string) $gearComponent->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function resolveUsageWindowsFor(GearComponent $gearComponent): UsageWindows
    {
        return $this->resolveUsageWindowsAlongPath($gearComponent, []);
    }

    /**
     * @param list<string> $path
     */
    private function resolveUsageWindowsAlongPath(GearComponent $gearComponent, array $path): UsageWindows
    {
        if (in_array((string) $gearComponent->getId(), $path, true)) {
            throw new GearComponentInstallationLoopDetected(sprintf('Component "%s" is installed on itself', $gearComponent->getLabel()));
        }
        $path[] = (string) $gearComponent->getId();

        $usageWindows = UsageWindows::empty();
        foreach ($gearComponent->getInstallations() as $installation) {
            $parent = $installation->getParent();
            if (($gearId = $parent->getGearId()) instanceof GearId) {
                $usageWindows->add(UsageWindow::create(
                    gearId: $gearId,
                    from: $installation->getFrom(),
                    until: $installation->getUntil(),
                    sportTypes: $installation->getSportTypes(),
                ));

                continue;
            }

            if (!($parentComponentId = $parent->getGearComponentId()) instanceof GearComponentId) {
                continue;
            }
            if (!($parentComponent = $this->findById($parentComponentId)) instanceof GearComponent) {
                continue;
            }

            foreach ($this->resolveUsageWindowsAlongPath($parentComponent, $path) as $parentUsageWindow) {
                if (($usageWindow = $parentUsageWindow->narrowedBy($installation)) instanceof UsageWindow) {
                    $usageWindows->add($usageWindow);
                }
            }
        }

        return $usageWindows;
    }

    private function findById(GearComponentId $gearComponentId): ?GearComponent
    {
        return $this->find(
            static fn (GearComponent $gearComponent): bool => (string) $gearComponent->getId() === (string) $gearComponentId
        );
    }
}
