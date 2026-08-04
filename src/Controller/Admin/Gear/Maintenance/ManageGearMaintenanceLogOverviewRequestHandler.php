<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogOverviewRepository;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearMaintenanceLogOverviewRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private Environment $twig,
        private GearMaintenanceLogOverviewRepository $gearMaintenanceLogOverviewRepository,
    ) {
    }

    #[Route(path: '/admin/gear/maintenance-logs', name: 'admin_manage_gear_maintenance_logs_overview', methods: ['GET'], priority: 10)]
    public function handle(Request $request): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/maintenance/manage-gear-maintenance-logs-overview.html.twig', [
            'overview' => $this->gearMaintenanceLogOverviewRepository->find(
                $this->paginationFromRequest($request)
            ),
        ]));
    }
}
