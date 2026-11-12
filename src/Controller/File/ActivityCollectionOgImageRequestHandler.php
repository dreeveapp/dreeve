<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionOpenGraphImage;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollection;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityCollectionOgImageRequestHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private ActivityCollectionOpenGraphImage $activityCollectionOpenGraphImage,
    ) {
    }

    #[Route(path: '/collections/{activityCollectionId}/og-image.png', name: 'activity_collection_og_image', requirements: ['activityCollectionId' => 'activityCollection-[^/]+'], methods: ['GET'])]
    public function handle(string $activityCollectionId): Response
    {
        $activityCollection = $this->activityCollectionRepository->find(ActivityCollectionId::fromString($activityCollectionId));
        if (0 === $this->queryBus->ask(new FindNumberOfActivitiesPerActivityCollection())->getNumberOfActivitiesFor($activityCollection->getId())) {
            throw new NotFoundHttpException('Not found');
        }

        $activityCollectionWithActivities = ActivityCollectionWithActivities::create(
            activityCollection: $activityCollection,
            activities: $this->activityRepository->findByIds($activityCollection->getActivityIds()),
        );

        return new Response($this->activityCollectionOpenGraphImage->render($activityCollectionWithActivities), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
