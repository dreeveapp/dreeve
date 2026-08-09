<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityPolylinesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/polylines', name: 'api_activity_polylines', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): JsonResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        if (!$activity->getLeafletMap() instanceof LeafletMap) {
            throw new NotFoundHttpException(sprintf('Activity "%s" has no map', $activityId));
        }

        $cacheKey = sprintf('activity.%s.polylines', $activity->getId()->toUnprefixedString());
        $render = $this->renderCache->get(
            cacheKey: $cacheKey,
            cacheability: Cacheability::for(
                cacheKey: $cacheKey,
                cacheTags: CacheTags::of(ActivityCacheTag::for($activity->getId())),
            ),
            // Resolved inside the callback so a cache hit never touches the lat/lng blob.
            callback: fn (): string => Json::encode([$this->routeCoordinates($activity)]),
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function routeCoordinates(Activity $activity): array
    {
        try {
            $latLng = $this->activityStreamRepository->findOneByActivityAndStreamType(
                activityId: $activity->getId(),
                streamType: StreamType::LAT_LNG,
            )->getData();

            if ($latLng) {
                return $latLng;
            }
        } catch (EntityNotFound) {
        }

        return $activity->getEncodedPolyline()?->decodeAndPairLatLng() ?? [];
    }
}
