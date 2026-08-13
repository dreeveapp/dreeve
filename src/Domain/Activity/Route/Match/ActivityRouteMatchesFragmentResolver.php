<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
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
    private const string BASE_PATH = 'activity';

    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/route-matches$#', $path, $matches)) {
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
            path: sprintf('%s/%s/route-matches', self::BASE_PATH, $activityId),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.route-matches', self::BASE_PATH, $activityId->toUnprefixedString()),
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
