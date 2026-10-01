<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\Eddington\EddingtonChart;
use App\Domain\Activity\Eddington\EddingtonHistoryChart;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class EddingtonRequestHandler
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/eddington', name: 'eddington', methods: ['GET'])]
    public function handle(): PrivateNoStoreHtmlResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'eddington',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_METRICS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new PrivateNoStoreHtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::EDDINGTON,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        $eddingtonCharts = [];
        $eddingtonHistoryCharts = [];
        $allEddingtons = $this->eddingtonCalculator->calculate(...UnitSystem::cases());

        foreach ($allEddingtons as $eddington) {
            $id = $eddington->getId();
            $eddingtonCharts[$id] = Json::encode(
                EddingtonChart::create(
                    eddington: $eddington,
                    unitSystem: $eddington->getUnitSystem(),
                    translator: $this->translator,
                )->build()
            );
            $eddingtonHistoryCharts[$id] = Json::encode(
                EddingtonHistoryChart::create(
                    eddington: $eddington,
                )->build()
            );
        }

        return $this->twig->load('html/eddington.html.twig')->render([
            'activeUnitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
            'eddingtons' => $allEddingtons,
            'eddingtonCharts' => $eddingtonCharts,
            'eddingtonHistoryCharts' => $eddingtonHistoryCharts,
        ]);
    }
}
