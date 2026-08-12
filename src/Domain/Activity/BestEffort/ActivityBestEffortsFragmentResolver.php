<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
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
    private const string BASE_PATH = 'activity';

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/best-efforts$#', $path, $matches)) {
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
            path: sprintf('%s/%s/best-efforts', self::BASE_PATH, $activityId),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.best-efforts', self::BASE_PATH, $activityId->toUnprefixedString()),
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
