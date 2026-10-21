<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Gear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Http\Api\ProvidePagination;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GearMaintenanceTaskSearchRequestHandler
{
    use ProvidePagination;

    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearComponentRepository $gearComponentRepository,
        private GearIdRepository $gearIdRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Clock $clock,
    ) {
    }

    #[Route(path: '/api/v1/gear/maintenance-tasks', name: 'api_v1_gear_maintenance_tasks', methods: ['GET'], priority: 3)]
    public function handle(Request $request): JsonResponse
    {
        $filterOnGearId = GearMaintenanceTaskSearchFilters::fromRequest($request)->getGearId();

        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return new JsonResponse([
                'maintenanceTasks' => [],
                'pagination' => self::buildPagination(Pagination::fromPageNumberAndSize(1, 1), 0),
            ]);
        }

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
        $total = count($maintenanceTasks);
        $pagination = Pagination::fromPageNumberAndSize(1, max(1, $total));

        return new JsonResponse([
            'maintenanceTasks' => array_column($maintenanceTasks, 'maintenanceTask'),
            'pagination' => self::buildPagination($pagination, $total),
        ]);
    }
}
