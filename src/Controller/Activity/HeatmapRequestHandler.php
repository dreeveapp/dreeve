<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class HeatmapRequestHandler
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/heatmap', name: 'heatmap', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'heatmap',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE, RootCacheTag::SETTINGS_MAPS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new PrivateNoStoreHtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::HEATMAP,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/heatmap.html.twig')->render([
            'summary' => $this->routeRepository->findSummary(),
            'heatmapConfig' => $this->settingsRepository->maps()->getHeatmapConfig(),
        ]);
    }
}
