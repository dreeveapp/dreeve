<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class ActivityMetricsRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/metrics', name: 'api_activity_metrics', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): JsonResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        $cacheKey = sprintf('activity.%s.metrics', $activity->getId()->toUnprefixedString());
        $render = $this->renderCache->get(
            cacheKey: $cacheKey,
            cacheability: Cacheability::for(
                cacheKey: $cacheKey,
                cacheTags: CacheTags::of(ActivityCacheTag::for($activity->getId())),
            ),
            callback: fn (): string => Json::encode($this->profileCharts($activity, $activityId)->build()),
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }

    private function profileCharts(Activity $activity, string $activityId): CombinedStreamProfileCharts
    {
        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        try {
            $combinedActivityStream = $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                activityId: $activity->getId(),
                unitSystem: $unitSystem,
            );
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" has no combined stream', $activityId));
        }

        $items = [];
        foreach ($combinedActivityStream->getStreamTypesForCharts() as $combinedStreamType) {
            $items[] = [
                'yAxisData' => $combinedActivityStream->getChartStreamData($combinedStreamType),
                'yAxisStreamType' => $combinedStreamType,
            ];
        }

        return CombinedStreamProfileCharts::create(
            items: array_reverse($items),
            topXAxisData: $combinedActivityStream->getTimes(),
            bottomXAxisData: $combinedActivityStream->getDistances(),
            bottomXAxisSuffix: $activity->getSportType()->distanceSymbol($unitSystem),
            grades: $combinedActivityStream->getGrades(),
            maximumNumberOfDigitsOnYAxis: $combinedActivityStream->getMaximumNumberOfDigits(),
            unitSystem: $unitSystem,
            sportType: $activity->getSportType(),
            athleteMaxHeartRate: $general->getAthlete()->getMaxHeartRate($activity->getStartDate()),
            heartRateZones: $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
                sportType: $activity->getSportType(),
                on: $activity->getStartDate()
            ),
            translator: $this->translator,
        );
    }
}
