<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use Twig\Environment;

final readonly class BestEffortsHistoryFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'best-efforts';

    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/(\d+)$#', $path, $matches)) {
            return null;
        }

        if (!$activityType = ActivityType::tryFrom($matches[1])) {
            return null;
        }

        $distanceInMeter = (int) $matches[2];
        $distance = array_find(
            $activityType->getDistancesForBestEffortCalculation(),
            fn (ConvertableToMeter $distance): bool => $distance->toMeter()->toInt() === $distanceInMeter
        );
        if (!$distance instanceof ConvertableToMeter) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/%s/%d', self::BASE_PATH, $activityType->value, $distanceInMeter),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.%d', self::BASE_PATH, $activityType->value, $distanceInMeter),
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->twig->load('html/best-efforts/best-efforts-history.html.twig')->render([
                'activityType' => $activityType,
                'period' => BestEffortPeriod::ALL_TIME,
                'distance' => $distance,
                'bestEfforts' => $this->bestEffortsCalculator->calculate(),
            ]),
            type: FragmentType::PAGE,
        );
    }
}
