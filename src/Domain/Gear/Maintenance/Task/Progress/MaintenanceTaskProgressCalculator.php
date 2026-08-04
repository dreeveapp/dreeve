<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearMaintenanceConfig;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
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
        private GearMaintenanceLogRepository $gearMaintenanceLogRepository,
        private GearIdRepository $gearIdRepository,
    ) {
    }

    public function calculateProgressFor(ProgressCalculationContext $context): MaintenanceTaskProgress
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

        return $this->calculateStatusesFor($gearMaintenanceConfig);
    }

    public function getGearIdsThatHaveDueTasks(): GearIds
    {
        $gearIdsThatHaveDueTasks = GearIds::empty();
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return $gearIdsThatHaveDueTasks;
        }

        $maintenanceTaskStatuses = $this->calculateStatusesFor($gearMaintenanceConfig);
        $retiredGearIds = $gearMaintenanceConfig->ignoreRetiredGear()
            ? $this->gearIdRepository->findRetired()
            : GearIds::empty();

        foreach ($gearMaintenanceConfig->getGearComponents() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                if (!$maintenanceTaskStatuses->getForTask($maintenanceTask->getId())?->isDue()) {
                    continue;
                }

                foreach ($gearComponent->getAttachedTo() as $gearId) {
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

    private function calculateStatusesFor(GearMaintenanceConfig $gearMaintenanceConfig): MaintenanceTaskStatuses
    {
        $mostRecentPerMaintenanceTask = $this->gearMaintenanceLogRepository->findMostRecentPerMaintenanceTask();

        $maintenanceTaskStatuses = MaintenanceTaskStatuses::empty();
        foreach ($gearMaintenanceConfig->getGearComponents() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                $lastPerformedOn = $mostRecentPerMaintenanceTask[(string) $maintenanceTask->getId()] ?? null;

                $maintenanceTaskStatuses->add(MaintenanceTaskStatus::from(
                    maintenanceTaskId: $maintenanceTask->getId(),
                    lastPerformedOn: $lastPerformedOn,
                    progress: $lastPerformedOn instanceof SerializableDateTime ? $this->calculateProgressFor(
                        ProgressCalculationContext::from(
                            gearIds: $gearComponent->getAttachedTo(),
                            lastTaggedOn: $lastPerformedOn,
                            intervalUnit: $maintenanceTask->getIntervalUnit(),
                            intervalValue: $maintenanceTask->getIntervalValue(),
                        )
                    ) : MaintenanceTaskProgress::from(0, '0'),
                ));
            }
        }

        return $maintenanceTaskStatuses;
    }
}
