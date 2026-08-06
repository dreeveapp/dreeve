<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Twig\UrlTwigExtension;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class HeatmapApiRequestHandler
{
    private const string CACHE_KEY = 'heatmap.routes';

    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private UrlTwigExtension $urlTwigExtension,
        private RenderCache $renderCache,
    ) {
    }

    #[Route(path: '/api/heatmap/routes', name: 'api_heatmap_routes', methods: ['GET'], priority: 3)]
    public function routes(): JsonResponse
    {
        $render = $this->renderCache->get(
            cacheKey: self::CACHE_KEY,
            cacheability: Cacheability::for(
                cacheKey: self::CACHE_KEY,
                cacheTags: CacheTags::of(CacheTag::ACTIVITY_ROUTE),
            ),
            callback: fn (): string => Json::encode($this->enrichedRoutes()),
        );

        return new JsonResponse($render->getContent() ?? '[]', json: true);
    }

    /**
     * @return \App\Domain\Activity\Route\Route[]
     */
    private function enrichedRoutes(): array
    {
        $appearance = $this->settingsRepository->appearance();

        $enrichedRoutes = [];
        foreach ($this->routeRepository->findAll() as $route) {
            $enrichedRoutes[] = $route
                ->withUnitSystemAndDateTimeFormat(
                    unitSystem: $appearance->getUnitSystem(),
                    dateAndTimeFormat: $appearance->getDateAndTimeFormat(),
                )
                ->withRelativeActivityUri($this->urlTwigExtension->toRelativeUrl('activity/'.$route->getActivityId().'.html'));
        }

        return $enrichedRoutes;
    }
}
