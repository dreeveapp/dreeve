<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Segment;

use App\Domain\Activity\LeafletMap;
use App\Domain\Segment\SegmentFragmentPath;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SegmentPolylinesRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/segments/{segmentId}/polylines', name: 'segment_polylines', requirements: ['segmentId' => 'segment-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $segmentId): Response
    {
        try {
            $segment = $this->segmentRepository->find(SegmentId::fromString($segmentId));
        } catch (EntityNotFound) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if (!$segment->getLeafletMap() instanceof LeafletMap) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: SegmentFragmentPath::for($segment->getId(), 'polylines'),
            cacheability: Cacheability::for(
                cacheKey: SegmentFragmentPath::cacheKey($segment->getId(), 'polylines'),
                cacheTags: CacheTags::of(RootCacheTag::SEGMENTS),
            ),
            render: fn (): string => Json::encode([$segment->getPolyline()?->decodeAndPairLatLng()]),
        ));

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }
}
