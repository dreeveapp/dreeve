<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\DistributionChartsBuilder;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Activity\GpxFileName;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class ActivityRequestHandler
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
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/activities/{activityId}', name: 'activity', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): PrivateNoStoreHtmlResponse
    {
        $activityId = ActivityId::fromString($activityId);

        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            throw new NotFoundHttpException('Not found');
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::GEAR,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor($activityId),
        );

        return new PrivateNoStoreHtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::ACTIVITIES,
                openGraph: new OpenGraph(
                    path: $this->urlGenerator->generate('activity', ['activityId' => (string) $activity->getId()]),
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
                    imagePath: $this->urlGenerator->generate('activity_og_image', ['activityId' => (string) $activity->getId()]),
                ),
            ),
            headers: $render->getCacheHeaders(),
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
                'polylineUrl' => $this->urlGenerator->generate('activity_polylines', ['activityId' => (string) $activityId]),
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
