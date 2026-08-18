<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;

final readonly class ActivityPolylinesFragmentResolver implements FragmentResolver
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$activityId = ActivityFragmentPath::match($path, 'polylines')) {
            return null;
        }

        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            return null;
        }

        if (!$activity->getLeafletMap() instanceof LeafletMap) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, 'polylines'),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, 'polylines'),
                cacheTags: CacheTags::of(ActivityCacheTag::for($activityId)),
            ),
            render: fn (): string => Json::encode([$this->routeCoordinates($activity)]),
            type: FragmentType::DATA,
        );
    }

    /**
     * @return array<mixed>
     */
    private function routeCoordinates(Activity $activity): array
    {
        try {
            $latLng = $this->activityStreamRepository->findOneByActivityAndStreamType(
                activityId: $activity->getId(),
                streamType: StreamType::LAT_LNG,
            )->getData();

            if ($latLng) {
                return $latLng;
            }
        } catch (EntityNotFound) {
        }

        return $activity->getEncodedPolyline()?->decodeAndPairLatLng() ?? [];
    }
}
