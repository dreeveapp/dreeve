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
    private const string BASE_PATH = 'activities';

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/polylines$#', $path, $matches)) {
            return null;
        }

        try {
            $activityId = ActivityId::fromString($matches[1]);
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound|\InvalidArgumentException) {
            return null;
        }

        if (!$activity->getLeafletMap() instanceof LeafletMap) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/%s/polylines', self::BASE_PATH, $activityId),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.polylines', self::BASE_PATH, $activityId->toUnprefixedString()),
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
