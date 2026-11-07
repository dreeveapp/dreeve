<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Collection;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollection;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageActivityCollectionOverviewRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityIdRepository $activityIdRepository,
        private QueryBus $queryBus,
    ) {
    }

    #[Route(path: '/admin/collections', name: 'admin_manage_activity_collection_overview', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/activity-collection/manage-activity-collection-overview.html.twig', [
            'activityCollections' => $this->activityCollectionRepository->findAll(),
            'numberOfActivities' => $this->queryBus->ask(new FindNumberOfActivitiesPerActivityCollection()),
            'hasActivities' => $this->activityIdRepository->count() > 0,
        ]));
    }
}
