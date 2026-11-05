<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity\Collection;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionCacheTag;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityCollectionPolylinesRequestHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityRepository $activityRepository,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/collections/{activityCollectionId}/polylines', name: 'activity_collection_polylines', requirements: ['activityCollectionId' => 'activityCollection-[^/]+'], methods: ['GET'])]
    public function handle(string $activityCollectionId): JsonResponse
    {
        $activityCollection = $this->activityCollectionRepository->find(ActivityCollectionId::fromString($activityCollectionId));
        $activities = $this->activityRepository->findByIds($activityCollection->getActivityIds());

        if ($activities->isEmpty()) {
            throw new NotFoundHttpException('Not found');
        }

        $activitiesWithARealWorldRoute = ActivityCollectionWithActivities::create(
            activityCollection: $activityCollection,
            activities: $activities,
        )->getActivitiesWithARealWorldRoute();

        if ($activitiesWithARealWorldRoute->isEmpty()) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activity-collections.%s.polylines', $activityCollection->getId()->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCollectionCacheTag::for($activityCollection->getId()),
                    RootCacheTag::ACTIVITY_ROUTE,
                    ...$activities->map(static fn (Activity $activity): CacheTag => ActivityCacheTag::for($activity->getId())),
                ),
            ),
            render: fn (): string => Json::encode($activitiesWithARealWorldRoute->map(
                static fn (Activity $activity): array => $activity->getEncodedPolyline()?->decodeAndPairLatLng() ?? [],
            )),
        );

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }
}
