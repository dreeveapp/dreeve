<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class ActivitySegmentsFragmentResolver implements FragmentResolver
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$activityId = ActivityFragmentPath::match($path, 'segments')) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'segments'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'segments'),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::SEGMENTS,
                ),
            ),
            render: fn (): string => $this->renderFor($activityId),
            type: FragmentType::PARTIAL,
        );
    }

    private function renderFor(ActivityId $activityId): string
    {
        $activity = $this->activityRepository->find($activityId);

        return $this->twig->load('html/activity/_segments.html.twig')->render([
            'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activityId),
            'sportType' => $activity->getSportType(),
        ]);
    }
}
