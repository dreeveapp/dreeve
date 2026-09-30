<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityPolylinesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/polylines', name: 'activity_polylines', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if (!$activity->getLeafletMap() instanceof LeafletMap) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'polylines'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'polylines'),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode([$this->routeCoordinates($activity)]),
        ));

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
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

            if ([] !== $latLng) {
                return $latLng;
            }
        } catch (EntityNotFound) {
        }

        return $activity->getEncodedPolyline()?->decodeAndPairLatLng() ?? [];
    }
}
