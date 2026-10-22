<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Time\Clock\Clock;

final readonly class ListGearMaintenanceTasks implements AITool
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearComponentRepository $gearComponentRepository,
        private GearIdRepository $gearIdRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Clock $clock,
    ) {
    }

    public function getName(): string
    {
        return 'list_gear_maintenance_tasks';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the gear maintenance tasks, most urgent first, with how far each one has progressed towards its interval and whether it is due or overdue.
            Optionally restrict it to the tasks of a single piece of gear.
            Use this tool when the user asks what maintenance is coming up, for example “Do I need to replace my chain?” or “What is overdue on my bike?”
            Returns an empty list when gear maintenance is not enabled.
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('gearId', 'Only include the tasks of this gear.'),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return ['maintenanceTasks' => []];
        }

        $filterOnGearId = $arguments->getGearId();
        $now = $this->clock->getCurrentDateTimeImmutable();
        $gearComponents = $this->gearComponentRepository->findAll();
        $maintenanceTaskStatuses = $this->maintenanceTaskProgressCalculator->calculateStatuses();
        $retiredGearIds = $gearMaintenanceConfig->ignoreRetiredGear()
            ? $this->gearIdRepository->findRetired()
            : GearIds::empty();

        $maintenanceTasks = [];
        foreach ($gearComponents->withoutRetired() as $gearComponent) {
            $currentGearIds = $gearComponents->getGearIdsCurrentlyUsing($gearComponent, $now);
            $gearIds = $currentGearIds->filter(static fn (GearId $gearId): bool => !$retiredGearIds->has($gearId));
            if (!$currentGearIds->isEmpty() && $gearIds->isEmpty()) {
                continue;
            }
            if ($filterOnGearId instanceof GearId && !$gearIds->has($filterOnGearId)) {
                continue;
            }

            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                $maintenanceTaskStatus = $maintenanceTaskStatuses->getForTask($maintenanceTask->getId());
                $progress = $maintenanceTaskStatus?->getProgress();

                $maintenanceTasks[] = [
                    'completionRatio' => $progress?->getCompletionRatio() ?? -1.0,
                    'maintenanceTask' => [
                        'id' => (string) $maintenanceTask->getId(),
                        'label' => (string) $maintenanceTask->getLabel(),
                        'component' => [
                            'id' => (string) $gearComponent->getId(),
                            'label' => (string) $gearComponent->getLabel(),
                        ],
                        'gearIds' => array_values($gearIds->map(static fn (GearId $gearId): string => (string) $gearId)),
                        'interval' => [
                            'value' => $maintenanceTask->getIntervalValue(),
                            'unit' => $maintenanceTask->getIntervalUnit()->value,
                        ],
                        'lastPerformedOn' => $maintenanceTaskStatus?->getLastPerformedOn()?->format('Y-m-d'),
                        'elapsed' => $progress instanceof MaintenanceTaskProgress ? round($progress->getElapsed(), 1) : null,
                        'remaining' => $progress instanceof MaintenanceTaskProgress ? round($progress->getRemaining(), 1) : null,
                        'percentage' => $progress?->getPercentage(),
                        'isDue' => $progress?->isDue() ?? false,
                        'isOverdue' => $progress?->isOverdue() ?? false,
                    ],
                ];
            }
        }

        usort($maintenanceTasks, static fn (array $a, array $b): int => $b['completionRatio'] <=> $a['completionRatio']);

        return [
            'maintenanceTasks' => array_column($maintenanceTasks, 'maintenanceTask'),
        ];
    }
}
