<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class HeatmapTilesRequestHandler
{
    public function __construct(
        private ActivityExplorerTilesRepository $activityExplorerTilesRepository,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/heatmap/tiles', name: 'heatmap_tiles', methods: ['GET'])]
    public function handle(): JsonResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'heatmap.tiles',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE),
            ),
            render: fn (): string => Json::encode((object) $this->activityExplorerTilesRepository->findAllOnHeatmap()),
        );

        return new JsonResponse($render->getContent() ?? '{}', headers: $render->getCacheHeaders(), json: true);
    }
}
