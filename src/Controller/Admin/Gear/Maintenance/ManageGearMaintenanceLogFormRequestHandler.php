<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog\AddGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog\DeleteGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogOverviewRepository;
use App\Domain\Gear\Maintenance\Log\UpdateGearMaintenanceLog\UpdateGearMaintenanceLog;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearMaintenanceLogFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearRepository $gearRepository,
        private GearMaintenanceLogOverviewRepository $gearMaintenanceLogOverviewRepository,
    ) {
    }

    #[Route(path: '/admin/gear/maintenance-logs/register', name: 'admin_register_gear_maintenance_log', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/edit-gear-maintenance-log.html.twig', [
            'dispatchCommand' => AddGearMaintenanceLog::getCommandName(),
            'components' => $this->buildComponentOptions(),
        ]));
    }

    #[Route(path: '/admin/gear/maintenance-logs/{gearMaintenanceLogId}/edit', name: 'admin_edit_gear_maintenance_log', methods: ['GET'], priority: 10)]
    public function handleEdit(string $gearMaintenanceLogId): HtmlResponse
    {
        $gearMaintenanceLog = $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId(
            GearMaintenanceLogId::fromString($gearMaintenanceLogId)
        );

        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/edit-gear-maintenance-log.html.twig', [
            'dispatchCommand' => UpdateGearMaintenanceLog::getCommandName(),
            'gearMaintenanceLog' => $gearMaintenanceLog,
            'componentLabel' => $gearMaintenanceLog->getComponentLabel(),
            'taskLabel' => $gearMaintenanceLog->getTaskLabel(),
            'gearName' => $gearMaintenanceLog->getGearName(),
        ]));
    }

    #[Route(path: '/admin/gear/maintenance-logs/{gearMaintenanceLogId}/delete', name: 'admin_delete_gear_maintenance_log', methods: ['GET'], priority: 10)]
    public function handleDelete(string $gearMaintenanceLogId): HtmlResponse
    {
        $gearMaintenanceLog = $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId(
            GearMaintenanceLogId::fromString($gearMaintenanceLogId)
        );

        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/delete-gear-maintenance-log.html.twig', [
            'dispatchCommand' => DeleteGearMaintenanceLog::getCommandName(),
            'gearMaintenanceLog' => $gearMaintenanceLog,
            'componentLabel' => $gearMaintenanceLog->getComponentLabel(),
            'taskLabel' => $gearMaintenanceLog->getTaskLabel(),
            'gearName' => $gearMaintenanceLog->getGearName(),
        ]));
    }

    /**
     * @return list<array{label: string, tasks: list<array{id: string, label: string}>, gears: list<array{id: string, label: string}>}>
     */
    private function buildComponentOptions(): array
    {
        $gears = $this->gearRepository->findAll();

        $components = [];
        foreach ($this->gearMaintenanceRepository->find()->getGearComponents() as $gearComponent) {
            $tasks = [];
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                $tasks[] = [
                    'id' => (string) $maintenanceTask->getId(),
                    'label' => (string) $maintenanceTask->getLabel(),
                ];
            }

            $componentGears = [];
            foreach ($gearComponent->getAttachedTo() as $gearId) {
                if (!($gear = $gears->getByGearId($gearId)) instanceof Gear) {
                    continue;
                }
                $componentGears[] = [
                    'id' => (string) $gear->getId(),
                    'label' => $gear->getName(),
                ];
            }
            if ([] === $tasks) {
                continue;
            }
            if ([] === $componentGears) {
                continue;
            }

            $components[] = [
                'label' => (string) $gearComponent->getLabel(),
                'tasks' => $tasks,
                'gears' => $componentGears,
            ];
        }

        return $components;
    }
}
