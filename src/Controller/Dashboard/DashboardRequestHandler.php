<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class DashboardRequestHandler
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'], priority: 3)]
    #[Route(path: '/dashboard', name: 'dashboard', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: 'dashboard',
                cacheTags: CacheTags::of(RootCacheTag::DASHBOARD),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::DASHBOARD,
                openGraph: null,
            ),
            headers: [...$render->getCacheHeaders(), 'Cache-Control' => 'private, no-store'],
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/dashboard/dashboard.html.twig')->render([
            'configuredWidgets' => $this->configuredWidgets,
        ]);
    }
}
