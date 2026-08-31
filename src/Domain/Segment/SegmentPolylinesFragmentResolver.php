<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\LeafletMap;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;

final readonly class SegmentPolylinesFragmentResolver implements FragmentResolver
{
    public function __construct(
        private SegmentRepository $segmentRepository,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!($segmentId = SegmentFragmentPath::match($path, 'polylines')) instanceof SegmentId) {
            return null;
        }

        try {
            $segment = $this->segmentRepository->find($segmentId);
        } catch (EntityNotFound) {
            return null;
        }

        if (!$segment->getLeafletMap() instanceof LeafletMap) {
            return null;
        }

        return new ResolvedFragment(
            path: SegmentFragmentPath::for($segment->getId(), 'polylines'),
            cacheability: Cacheability::for(
                cacheKey: SegmentFragmentPath::cacheKey($segment->getId(), 'polylines'),
                cacheTags: CacheTags::of(RootCacheTag::SEGMENTS),
            ),
            render: fn (): string => Json::encode([$segment->getPolyline()?->decodeAndPairLatLng()]),
            type: FragmentType::DATA,
        );
    }
}
