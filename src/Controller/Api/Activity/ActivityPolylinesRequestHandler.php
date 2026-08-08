<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
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
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/polylines', name: 'api_activity_polylines', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): JsonResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromPrefixedOrUnprefixed($activityId));
        } catch (EntityNotFound) {
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
            // Resolved inside the callback so a cache hit never touches the stream blob.
            callback: fn (): string => Json::encode([$this->routeCoordinates($activity)]),
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }

    /**
     * Unlike metrics and coordinates, this one still has an answer when there is no combined
     * stream: it falls back to the (privacy truncated) polyline Strava hands us.
     *
     * @return array<mixed>|null
     */
    private function routeCoordinates(Activity $activity): ?array
    {
        try {
            $coordinates = $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                activityId: $activity->getId(),
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
            )->getCoordinates();

            if ($coordinates) {
                return $coordinates;
            }
        } catch (EntityNotFound) {
        }

        return $activity->getEncodedPolyline()?->decodeAndPairLatLng();
    }
}
