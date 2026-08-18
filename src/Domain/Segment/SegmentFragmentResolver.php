<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\EnrichedActivity;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortHistoryChart;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentEffort\SegmentEfforts;
use App\Domain\Segment\SegmentEffort\SegmentEffortVsHeartRateChart;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SegmentFragmentResolver implements FragmentResolver
{
    private const int NUMBER_OF_TOP_EFFORTS = 10;

    public function __construct(
        private SegmentRepository $segmentRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private EnrichedActivityRepository $enrichedActivityRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$segmentId = SegmentFragmentPath::match($path)) {
            return null;
        }

        try {
            $segment = $this->segmentRepository->find($segmentId);
        } catch (EntityNotFound) {
            return null;
        }

        $topTenSegmentEfforts = $this->segmentEffortRepository->findTopXBySegmentId(
            $segment->getId(),
            self::NUMBER_OF_TOP_EFFORTS
        );

        return new ResolvedFragment(
            path: SegmentFragmentPath::for($segment->getId()),
            cacheability: Cacheability::for(
                cacheKey: SegmentFragmentPath::cacheKey($segment->getId()),
                cacheTags: CacheTags::of(
                    SegmentCacheTag::for($segment->getId()),
                    RootCacheTag::GEAR,
                    // The top ten renders the title and gear of every activity it lists, so a
                    // rename of one of those has to invalidate this segment too.
                    ...array_map(
                        static fn (SegmentEffort $segmentEffort): CacheTag => ActivityCacheTag::for($segmentEffort->getActivityId()),
                        $topTenSegmentEfforts->toArray(),
                    ),
                ),
            ),
            render: fn (): string => $this->renderFor($segment, $topTenSegmentEfforts),
        );
    }

    private function renderFor(Segment $segment, SegmentEfforts $topTenSegmentEfforts): string
    {
        $segmentEfforts = $this->segmentEffortRepository->findBySegmentId($segment->getId());
        $segment = $segment
            ->withNumberOfTimesRidden(count($segmentEfforts))
            ->withBestEffort($topTenSegmentEfforts->getBestEffort())
            ->withLastEffortDate($segmentEfforts->getFirst()?->getStartDateTime());

        $leafletMap = $segment->getLeafletMap();

        return $this->twig->load('html/segment/segment.html.twig')->render([
            'segment' => $segment,
            'segmentEffortsTopTen' => $topTenSegmentEfforts,
            'enrichedActivitiesPerSegmentEffortId' => $this->enrichedActivitiesPerSegmentEffortId($topTenSegmentEfforts),
            'segmentEffortsVsHeartRateChart' => Json::encode(
                SegmentEffortVsHeartRateChart::create(
                    segmentEfforts: $segmentEfforts,
                    sportType: $segment->getSportType(),
                    unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                    translator: $this->translator
                )->build()
            ),
            'segmentEffortsHistoryChart' => Json::encode(
                SegmentEffortHistoryChart::create($segmentEfforts)->build()
            ),
            'leaflet' => $leafletMap ? [
                'polylineUrl' => SegmentFragmentPath::for($segment->getId(), 'polylines'),
                'map' => $leafletMap,
            ] : null,
        ]);
    }

    /**
     * @return array<string, EnrichedActivity>
     */
    private function enrichedActivitiesPerSegmentEffortId(SegmentEfforts $segmentEfforts): array
    {
        $activityIds = ActivityIds::fromArray(array_map(
            fn (SegmentEffort $segmentEffort): ActivityId => $segmentEffort->getActivityId(),
            $segmentEfforts->toArray()
        ));

        $enrichedActivitiesPerActivityId = [];
        foreach ($this->enrichedActivityRepository->findByIds($activityIds) as $enrichedActivity) {
            $enrichedActivitiesPerActivityId[(string) $enrichedActivity->getActivity()->getId()] = $enrichedActivity;
        }

        $enrichedActivities = [];
        foreach ($segmentEfforts as $segmentEffort) {
            $enrichedActivities[(string) $segmentEffort->getId()] = $enrichedActivitiesPerActivityId[(string) $segmentEffort->getActivityId()];
        }

        return $enrichedActivities;
    }
}
