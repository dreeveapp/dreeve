<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
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
use App\Infrastructure\Security\AuthenticatedVisitor;
use App\Infrastructure\ValueObject\String\Slug;
use Twig\Environment;

final readonly class ActivityFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'activity';

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
        private AuthenticatedVisitor $authenticatedVisitor,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)$#', $path, $matches)) {
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

        return new ResolvedFragment(
            path: self::BASE_PATH.'/'.$activityId,
            cacheability: $this->cacheabilityFor($activityId),
            render: fn (): string => $this->renderFor($activityId),
        );
    }

    private function cacheabilityFor(ActivityId $activityId): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('%s.%s', self::BASE_PATH, $activityId->toUnprefixedString()),
            cacheTags: CacheTags::of(
                ActivityCacheTag::for($activityId),
                RootCacheTag::GEAR,
            ),
            cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
        );
    }

    private function renderFor(ActivityId $activityId): string
    {
        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);
        $activity = $enrichedActivity->getActivity();

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $leafletMap = $activity->getLeafletMap();
        $numberOfProfileChartLanes = $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            $activityId,
            $unitSystem
        );

        $timeInHeartRateZones = null;
        try {
            $timeInHeartRateZones = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId);
        } catch (EntityNotFound) {
        }

        $templateName = sprintf('html/activity/%s.html.twig', $activity->getSportType()->getTemplateName());

        return $this->twig->load($templateName)->render([
            'activity' => $activity,
            'enrichedActivity' => $enrichedActivity,
            'leaflet' => $leafletMap instanceof LeafletMap ? [
                'polylineUrl' => sprintf('activity/%s/polylines', $activityId),
                'map' => $leafletMap,
            ] : null,
            'gpxLink' => $this->activityStreamRepository->hasOneForActivityAndStreamType($activityId, StreamType::TIME)
                ? sprintf('api/activity/%s/route.gpx', $activityId)
                : null,
            'gpxFileName' => sprintf(
                '%s-%s.gpx',
                $activity->getStartDate()->format('Y-m-d'),
                Slug::fromString($activity->getName()),
            ),
            'distributionCharts' => $this->distributionChartsBuilder->buildFor($activity),
            'splits' => $this->activitySplitRepository->findBy(
                activityId: $activityId,
                unitSystem: $unitSystem
            ),
            'laps' => $this->activityLapRepository->findBy($activityId),
            'profileChartHeight' => CombinedStreamProfileCharts::totalHeightFor($numberOfProfileChartLanes),
            'hasProfileChart' => $numberOfProfileChartLanes > 0,
            'heartRateZones' => $timeInHeartRateZones,
            'isAuthenticated' => $this->authenticatedVisitor->isAuthenticated(),
        ]);
    }
}
