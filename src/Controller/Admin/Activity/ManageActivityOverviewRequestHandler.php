<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity;

use App\Domain\Activity\ActivityOverviewRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageActivityOverviewRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private Environment $twig,
        private ActivityOverviewRepository $activityOverviewRepository,
        private SportTypeRepository $sportTypeRepository,
        private GearRepository $gearRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
    ) {
    }

    #[Route(path: '/admin/activities', name: 'admin_manage_activity_overview', methods: ['GET'], priority: 10)]
    public function handle(Request $request): Response
    {
        $filters = ActivityOverviewFilters::fromRequest($request);

        return new Response($this->twig->render('html/admin/page/activity/manage-activities-overview.html.twig', [
            'overview' => $this->activityOverviewRepository->find(
                $this->paginationFromRequest($request),
                $filters,
            ),
            'filters' => $filters,
            'sportTypeOptions' => $this->sportTypeRepository->findAll(),
            'gearOptions' => $this->gearRepository->findAllUsed(),
            'deviceOptions' => $this->recordingDeviceRepository->findAll(),
            'importSourceOptions' => ImportSource::cases(),
        ]));
    }
}
