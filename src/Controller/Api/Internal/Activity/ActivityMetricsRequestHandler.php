<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/metrics', name: 'activity_metrics', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Not found');
        }

        if (0 === $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            $activityId,
            $this->settingsRepository->appearance()->getUnitSystem(),
        )) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.metrics', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode($this->profileChartsFor($activityId)->build()),
        );

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }

    private function profileChartsFor(ActivityId $activityId): CombinedStreamProfileCharts
    {
        $activity = $this->activityRepository->find($activityId);
        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $combinedActivityStream = $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
            activityId: $activityId,
            unitSystem: $unitSystem,
        );

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
            temperatures: $combinedActivityStream->getTemperatures(),
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
