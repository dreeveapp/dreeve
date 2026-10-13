<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearComponents;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class MaintenanceTaskProgressCalculator
{
    /**
     * @param iterable<MaintenanceTaskProgressCalculation> $maintenanceTaskProgressCalculations
     */
    public function __construct(
        #[AutowireIterator('app.maintenance_progress_calculation')]
        private iterable $maintenanceTaskProgressCalculations,
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearComponentRepository $gearComponentRepository,
        private GearMaintenanceLogRepository $gearMaintenanceLogRepository,
        private GearIdRepository $gearIdRepository,
        private Clock $clock,
    ) {
    }

    public function calculateProgressFor(ProgressCalculationContext $context): ?MaintenanceTaskProgress
    {
        $intervalUnit = $context->getIntervalUnit();

        foreach ($this->maintenanceTaskProgressCalculations as $calculation) {
            if (!$calculation->supports($intervalUnit)) {
                continue;
            }

            return $calculation->calculate($context);
        }

        throw new \RuntimeException(sprintf('No progress calculation found for interval unit: %s', $intervalUnit->value));
    }

    public function calculateStatuses(): MaintenanceTaskStatuses
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return MaintenanceTaskStatuses::empty();
        }

        return $this->calculateStatusesFor($this->gearComponentRepository->findAll());
    }

    public function getGearIdsThatHaveDueTasks(): GearIds
    {
        $gearIdsThatHaveDueTasks = GearIds::empty();
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return $gearIdsThatHaveDueTasks;
        }

        $gearComponents = $this->gearComponentRepository->findAll();
        $maintenanceTaskStatuses = $this->calculateStatusesFor($gearComponents);
        $retiredGearIds = $gearMaintenanceConfig->ignoreRetiredGear()
            ? $this->gearIdRepository->findRetired()
            : GearIds::empty();

        $now = $this->clock->getCurrentDateTimeImmutable();
        foreach ($gearComponents->withoutRetired() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                if (!$maintenanceTaskStatuses->getForTask($maintenanceTask->getId())?->isDue()) {
                    continue;
                }

                foreach ($gearComponents->getGearIdsCurrentlyUsing($gearComponent, $now) as $gearId) {
                    if ($retiredGearIds->has($gearId)) {
                        continue;
                    }
                    if ($gearIdsThatHaveDueTasks->has($gearId)) {
                        continue;
                    }
                    $gearIdsThatHaveDueTasks->add($gearId);
                }
            }
        }

        return $gearIdsThatHaveDueTasks;
    }

    private function calculateStatusesFor(GearComponents $gearComponents): MaintenanceTaskStatuses
    {
        $mostRecentPerMaintenanceTask = $this->gearMaintenanceLogRepository->findMostRecentPerMaintenanceTask();

        $maintenanceTaskStatuses = MaintenanceTaskStatuses::empty();
        foreach ($gearComponents->withoutRetired() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                $lastPerformedOn = $mostRecentPerMaintenanceTask[(string) $maintenanceTask->getId()] ?? null;

                $maintenanceTaskStatuses->add(MaintenanceTaskStatus::from(
                    maintenanceTaskId: $maintenanceTask->getId(),
                    lastPerformedOn: $lastPerformedOn,
                    progress: $this->calculateProgressFor(
                        ProgressCalculationContext::from(
                            usageWindows: $gearComponents->resolveUsageWindowsFor($gearComponent),
                            lastTaggedOn: $lastPerformedOn,
                            intervalUnit: $maintenanceTask->getIntervalUnit(),
                            intervalValue: $maintenanceTask->getIntervalValue(),
                        )
                    ),
                ));
            }
        }

        return $maintenanceTaskStatuses;
    }
}
