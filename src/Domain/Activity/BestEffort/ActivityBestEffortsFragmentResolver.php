<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class ActivityBestEffortsFragmentResolver implements FragmentResolver
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$activityId = ActivityFragmentPath::match($path, 'best-efforts')) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'best-efforts'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'best-efforts'),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->twig->load('html/activity/_best-efforts.html.twig')->render([
                'bestEfforts' => $this->activityBestEffortRepository->findByActivity($activityId),
            ]),
            type: FragmentType::PARTIAL,
        );
    }
}
