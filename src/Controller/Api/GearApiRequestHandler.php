<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class GearApiRequestHandler
{
    public function __construct(
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/gear/maintenance-due', name: 'api_gear_maintenance_due', methods: ['GET'], priority: 3)]
    public function maintenanceDue(): HtmlResponse
    {
        return new HtmlResponse($this->twig->load('html/gear/maintenance/_maintenance-due.html.twig')->render([
            'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
        ]));
    }
}
