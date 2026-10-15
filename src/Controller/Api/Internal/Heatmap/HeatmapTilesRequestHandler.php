<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
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
            render: function (): string {
                $visitedTiles = ExplorerTiles::union($this->activityExplorerTilesRepository->findAll());
                $clusters = $visitedTiles->clusters();
                $maxSquare = $visitedTiles->maxSquare();

                return Json::encode([
                    'visited' => $visitedTiles->without($clusters->getClusterTiles())->toArray(),
                    'cluster' => $clusters->getClusterTiles()->without($clusters->getMaxCluster())->toArray(),
                    'maxCluster' => $clusters->getMaxCluster()->toArray(),
                    'maxSquare' => $maxSquare,
                    'stats' => [
                        'visitedTiles' => $visitedTiles->count(),
                        'clusterTiles' => $clusters->getClusterTiles()->count(),
                        'maxCluster' => $clusters->getMaxCluster()->count(),
                        'maxSquare' => $maxSquare?->getSize() ?? 0,
                    ],
                ]);
            },
        );

        return new JsonResponse($render->getContent() ?? '{}', headers: $render->getCacheHeaders(), json: true);
    }
}
