<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTileHistory;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityNewExplorerTilesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ExplorerTileHistory $explorerTileHistory,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/explorer-tiles', name: 'activity_explorer_tiles', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): HtmlResponse
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.explorer-tiles', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITY_ROUTE,
                ),
            ),
            render: fn (): string => $this->twig->load('html/activity/_new-explorer-tiles.html.twig')->render([
                'newTileCount' => $this->explorerTileHistory->calculateNewTileCountFor($activityId),
            ]),
        );

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }
}
