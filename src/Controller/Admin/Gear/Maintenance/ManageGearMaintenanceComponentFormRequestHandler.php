<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent\CreateGearMaintenanceComponent;
use App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent\DeleteGearMaintenanceComponent;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent\UpdateGearMaintenanceComponent;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearMaintenanceComponentFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearRepository $gearRepository,
    ) {
    }

    #[Route(path: '/admin/gear/maintenance-config/component/add', name: 'admin_add_gear_maintenance_component', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/edit-gear-maintenance-component.html.twig', [
            'dispatchCommand' => CreateGearMaintenanceComponent::getCommandName(),
            'gears' => $this->gearRepository->findAll(),
        ]));
    }

    #[Route(path: '/admin/gear/maintenance-config/component/{gearComponentId}/edit', name: 'admin_edit_gear_maintenance_component', methods: ['GET'], priority: 10)]
    public function handleEdit(string $gearComponentId): HtmlResponse
    {
        $gearComponent = $this->gearMaintenanceRepository->findComponent(GearComponentId::fromString($gearComponentId));
        if (!$gearComponent instanceof GearComponent) {
            throw new NotFoundHttpException('Component not found');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/edit-gear-maintenance-component.html.twig', [
            'dispatchCommand' => UpdateGearMaintenanceComponent::getCommandName(),
            'component' => $gearComponent,
            'gears' => $this->gearRepository->findAll(),
        ]));
    }

    #[Route(path: '/admin/gear/maintenance-config/component/{gearComponentId}/delete', name: 'admin_delete_gear_maintenance_component', methods: ['GET'], priority: 10)]
    public function handleDelete(string $gearComponentId): HtmlResponse
    {
        $gearComponent = $this->gearMaintenanceRepository->findComponent(GearComponentId::fromString($gearComponentId));
        if (!$gearComponent instanceof GearComponent) {
            throw new NotFoundHttpException('Component not found');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/delete-gear-maintenance-component.html.twig', [
            'dispatchCommand' => DeleteGearMaintenanceComponent::getCommandName(),
            'component' => $gearComponent,
        ]));
    }
}
