<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Activity\LeafletMap;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SegmentApiRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/segment/{segmentId}/polylines', name: 'api_segment_polylines', methods: ['GET'], priority: 3)]
    public function polylines(string $segmentId): JsonResponse
    {
        try {
            $segment = $this->segmentRepository->find(SegmentId::fromString($segmentId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Segment "%s" not found', $segmentId));
        }

        if (!$segment->getLeafletMap() instanceof LeafletMap) {
            throw new NotFoundHttpException(sprintf('Segment "%s" has no map', $segmentId));
        }

        $cacheKey = sprintf('segment.%s.polylines', $segment->getId()->toUnprefixedString());
        $render = $this->renderCache->get(
            cacheKey: $cacheKey,
            cacheability: Cacheability::for(
                cacheKey: $cacheKey,
                cacheTags: CacheTags::empty(),
            ),
            callback: fn (): string => Json::encode([$segment->getPolyline()?->decodeAndPairLatLng()]),
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }
}
