<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ActivityFragmentResolver implements FragmentResolver
{
    use ProvideMeasurementFormats;

    public function __construct(
        private ActivityRepository $activityRepository,
        private EnrichedActivityRepository $enrichedActivityRepository,
        private DistributionChartsBuilder $distributionChartsBuilder,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private ActivitySplitRepository $activitySplitRepository,
        private ActivityLapRepository $activityLapRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!($activityId = ActivityFragmentPath::match($path)) instanceof ActivityId) {
            return null;
        }

        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            return null;
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::GEAR,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor($activityId),
            navigationSection: NavigationSection::ACTIVITIES,
            openGraph: new OpenGraph(
                path: ActivityFragmentPath::for($activity->getId()),
                title: $activity->getName(),
                description: implode(' · ', [
                    $activity->getSportType()->transSingular($this->translator),
                    $this->formatUnitWithSymbol(
                        $activity->getDistance()->toUnitSystem($unitSystem),
                        $activity->getSportType()->getActivityType()->getDistancePrecision(),
                    ),
                    $activity->getMovingTimeFormatted(),
                    $this->formatUnitWithSymbol($activity->getElevation()->toUnitSystem($unitSystem), 0),
                ]),
            ),
        );
    }

    private function renderFor(ActivityId $activityId): string
    {
        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);
        $activity = $enrichedActivity->getActivity();

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $leafletMap = $activity->getLeafletMap();
        $numberOfProfileChartLanes = $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            activityId: $activityId,
            unitSystem: $unitSystem
        );
        $hasTemperatureRibbon = $this->combinedActivityStreamRepository->hasStreamTypeFor(
            activityId: $activityId,
            unitSystem: $unitSystem,
            streamType: CombinedStreamType::TEMP
        );

        $timeInHeartRateZones = null;
        try {
            $timeInHeartRateZones = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId);
        } catch (EntityNotFound) {
        }

        $templateName = sprintf('html/activity/%s.html.twig', $activity->getSportType()->getTemplateName());

        return $this->twig->load($templateName)->render(context: [
            'activity' => $activity,
            'enrichedActivity' => $enrichedActivity,
            'leaflet' => $leafletMap instanceof LeafletMap ? [
                'polylineUrl' => ActivityFragmentPath::for($activityId, 'polylines'),
                'map' => $leafletMap,
            ] : null,
            'hasGpxLink' => $this->activityStreamRepository->hasOneForActivityAndStreamType($activityId, StreamType::TIME),
            'gpxFileName' => GpxFileName::for($activity),
            'distributionCharts' => $this->distributionChartsBuilder->buildFor($activity),
            'splits' => $this->activitySplitRepository->findBy(
                activityId: $activityId,
                unitSystem: $unitSystem
            ),
            'laps' => $this->activityLapRepository->findBy($activityId),
            'profileChartHeight' => CombinedStreamProfileCharts::totalHeightFor(
                numberOfLanes: $numberOfProfileChartLanes,
                hasTemperatureRibbon: $hasTemperatureRibbon
            ),
            'hasProfileChart' => $numberOfProfileChartLanes > 0,
            'heartRateZones' => $timeInHeartRateZones,
        ]);
    }
}
