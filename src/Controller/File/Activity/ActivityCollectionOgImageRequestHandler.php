<?php

declare(strict_types=1);

namespace App\Controller\File\Activity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionOpenGraphImage;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollection;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Response\CachePolicy;
use App\Infrastructure\Http\Response\PngResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Time\Duration;

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
    public function handle(string $activityCollectionId): PngResponse
    {
        $activityCollection = $this->activityCollectionRepository->find(ActivityCollectionId::fromString($activityCollectionId));
        if (0 === $this->queryBus->ask(new FindNumberOfActivitiesPerActivityCollection())->getNumberOfActivitiesFor($activityCollection->getId())) {
            throw new NotFoundHttpException('Not found');
        }

        $activityCollectionWithActivities = ActivityCollectionWithActivities::create(
            activityCollection: $activityCollection,
            activities: $this->activityRepository->findByIds($activityCollection->getActivityIds()),
        );

        return CachePolicy::public()->maxAge(Duration::fromHours(1))->applyTo(
            new PngResponse($this->activityCollectionOpenGraphImage->render($activityCollectionWithActivities)),
        );
    }
}
