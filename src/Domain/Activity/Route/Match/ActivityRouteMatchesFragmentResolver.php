<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match;

use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Match\FindRouteMatches\FindRouteMatches;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class ActivityRouteMatchesFragmentResolver implements FragmentResolver
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$activityId = ActivityFragmentPath::match($path, 'route-matches')) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'route-matches'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'route-matches'),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->twig->load('html/activity/_route-matches.html.twig')->render([
                'routeMatches' => $this->queryBus->ask(new FindRouteMatches($activityId))->getRouteMatches(),
            ]),
            type: FragmentType::PARTIAL,
        );
    }
}
