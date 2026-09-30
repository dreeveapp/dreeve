<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Twig\UrlTwigExtension;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class HeatmapRoutesRequestHandler
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private UrlTwigExtension $urlTwigExtension,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/heatmap/routes', name: 'heatmap_routes', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: 'heatmap/routes',
            cacheability: Cacheability::for(
                cacheKey: 'heatmap.routes',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }

    private function renderFor(): string
    {
        $appearance = $this->settingsRepository->appearance();

        $enrichedRoutes = [];
        foreach ($this->routeRepository->findAll() as $route) {
            $enrichedRoutes[] = $route
                ->withUnitSystemAndDateTimeFormat(
                    unitSystem: $appearance->getUnitSystem(),
                    dateAndTimeFormat: $appearance->getDateAndTimeFormat(),
                )
                ->withRelativeActivityUri($this->urlTwigExtension->toRelativeUrl(ActivityFragmentPath::for($route->getActivityId())));
        }

        return Json::encode($enrichedRoutes);
    }
}
