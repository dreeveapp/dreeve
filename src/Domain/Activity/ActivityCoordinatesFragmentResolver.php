<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;

final readonly class ActivityCoordinatesFragmentResolver implements FragmentResolver
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!($activityId = ActivityFragmentPath::match($path, 'coordinates')) instanceof ActivityId) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        if (0 === $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
            $activityId,
            $this->settingsRepository->appearance()->getUnitSystem(),
        )) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'coordinates'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'coordinates'),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode($this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                activityId: $activityId,
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
            )->getCoordinates()),
            type: FragmentType::DATA,
        );
    }
}
