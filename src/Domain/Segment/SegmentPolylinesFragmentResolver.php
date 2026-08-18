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
    private const string BASE_PATH = 'segments';

    public function __construct(
        private SegmentRepository $segmentRepository,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/polylines$#', $path, $matches)) {
            return null;
        }

        try {
            $segment = $this->segmentRepository->find(SegmentId::fromString($matches[1]));
        } catch (EntityNotFound|\InvalidArgumentException) {
            return null;
        }

        if (!$segment->getLeafletMap() instanceof LeafletMap) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/%s/polylines', self::BASE_PATH, $segment->getId()),
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.polylines', self::BASE_PATH, $segment->getId()->toUnprefixedString()),
                cacheTags: CacheTags::of(RootCacheTag::SEGMENTS),
            ),
            render: fn (): string => Json::encode([$segment->getPolyline()?->decodeAndPairLatLng()]),
            type: FragmentType::DATA,
        );
    }
}
