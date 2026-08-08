<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
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
final readonly class ActivityCoordinatesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/coordinates', name: 'api_activity_coordinates', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): JsonResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromPrefixedOrUnprefixed($activityId));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        $cacheKey = sprintf('activity.%s.coordinates', $activity->getId()->toUnprefixedString());
        $render = $this->renderCache->get(
            cacheKey: $cacheKey,
            cacheability: Cacheability::for(
                cacheKey: $cacheKey,
                cacheTags: CacheTags::of(ActivityCacheTag::for($activity->getId())),
            ),
            callback: function () use ($activity, $activityId): string {
                try {
                    return Json::encode($this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                        activityId: $activity->getId(),
                        unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                    )->getCoordinates());
                } catch (EntityNotFound) {
                    throw new NotFoundHttpException(sprintf('Activity "%s" has no combined stream', $activityId));
                }
            },
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }
}
