<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityMetricsFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'activities';

    public function __construct(
        private ActivityRepository $activityRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/metrics$#', $path, $matches)) {
            return null;
        }

        try {
            $activityId = ActivityId::fromString($matches[1]);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        if (0 === $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            $activityId,
            $this->settingsRepository->appearance()->getUnitSystem(),
        )) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/%s/metrics', self::BASE_PATH, $activityId),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.metrics', self::BASE_PATH, $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode($this->profileChartsFor($activityId)->build()),
            type: FragmentType::DATA,
        );
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
