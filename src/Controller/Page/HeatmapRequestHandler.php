<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Application\PageRenderer;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
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
final readonly class HeatmapRequestHandler
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/heatmap', name: 'heatmap', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'heatmap',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE, RootCacheTag::SETTINGS_MAPS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::HEATMAP,
            openGraph: new OpenGraph(
                path: $this->urlGenerator->generate('heatmap'),
                title: $this->translator->trans('Heatmap'),
                description: $this->translator->trans('A heatmap of all recorded routes'),
            ),
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
