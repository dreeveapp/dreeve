<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityCoordinatesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/coordinates', name: 'activity_coordinates', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if (0 === $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            $activityId,
            $this->settingsRepository->appearance()->getUnitSystem(),
        )) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.coordinates', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode($this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                activityId: $activityId,
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
            )->getCoordinates()),
        ));

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }
}
