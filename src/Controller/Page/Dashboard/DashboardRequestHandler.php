<?php

declare(strict_types=1);

namespace App\Controller\Page\Dashboard;

use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Application\PageRenderer;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class DashboardRequestHandler
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Environment $twig,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'])]
    #[Route(path: '/dashboard', name: 'dashboard', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'dashboard',
                cacheTags: CacheTags::of(RootCacheTag::DASHBOARD),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::DASHBOARD,
            openGraph: new OpenGraph(
                path: $this->urlGenerator->generate('dashboard'),
                title: $this->translator->trans('Dashboard'),
                description: $this->translator->trans('An overview of all activities, training load and statistics'),
            ),
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/dashboard/dashboard.html.twig')->render([
            'configuredWidgets' => $this->configuredWidgets,
        ]);
    }
}
