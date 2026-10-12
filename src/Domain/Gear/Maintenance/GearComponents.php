<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
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

    public function getGearIdsCurrentlyUsing(GearComponent $gearComponent, SerializableDateTime $now): GearIds
    {
        return $this->resolveUsageWindowsFor($gearComponent)->getGearIdsActiveOn($now);
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
            if ($gearId = $parent->getGearId()) {
                $usageWindows->add(UsageWindow::create(
                    gearId: $gearId,
                    from: $installation->getFrom(),
                    until: $installation->getUntil(),
                    sportTypes: $installation->getSportTypes(),
                ));

                continue;
            }

            $parentComponent = $this->find(
                static fn (GearComponent $candidate): bool => (string) $candidate->getId() === (string) $parent->getGearComponentId()
            );
            if (!$parentComponent instanceof GearComponent) {
                continue;
            }

            foreach ($this->resolveUsageWindowsAlongPath($parentComponent, $path) as $parentUsageWindow) {
                $from = $installation->getFrom();
                if (!$from || ($parentUsageWindow->getFrom() && $parentUsageWindow->getFrom()->isAfter($from))) {
                    $from = $parentUsageWindow->getFrom();
                }
                $until = $installation->getUntil();
                if (!$until || ($parentUsageWindow->getUntil() && $parentUsageWindow->getUntil()->isBefore($until))) {
                    $until = $parentUsageWindow->getUntil();
                }
                if ($from && $until && $until->isBeforeOrOn($from)) {
                    continue;
                }

                $sportTypes = $installation->getSportTypes();
                if ($sportTypes->isEmpty()) {
                    $sportTypes = $parentUsageWindow->getSportTypes();
                } elseif (!$parentUsageWindow->getSportTypes()->isEmpty()) {
                    $sportTypes = $sportTypes->filter(
                        static fn (SportType $sportType): bool => $parentUsageWindow->getSportTypes()->has($sportType)
                    );
                    if ($sportTypes->isEmpty()) {
                        continue;
                    }
                }

                $usageWindows->add(UsageWindow::create(
                    gearId: $parentUsageWindow->getGearId(),
                    from: $from,
                    until: $until,
                    sportTypes: $sportTypes,
                ));
            }
        }

        return $usageWindows;
    }
}
