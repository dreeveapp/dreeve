<?php

declare(strict_types=1);

namespace App\Controller\Badge;

use App\Application\AppShell;
use App\Application\AppUrl;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class BadgesRequestHandler
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private BestEffortsCalculator $bestEffortsCalculator,
        private AppUrl $appUrl,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/badges', name: 'badges', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: 'badges',
            cacheability: Cacheability::for(
                cacheKey: 'badges',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_ZWIFT),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: null,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/badges.html.twig')->render([
            'zwiftLevel' => $this->settingsRepository->zwift()->getZwiftLevel(),
            'appUrl' => rtrim((string) $this->appUrl, '/'),
            'sportTypesThatHaveBestEfforts' => $this->bestEffortsCalculator->calculate()->getAllSportTypesFor(BestEffortPeriod::ALL_TIME),
        ]);
    }
}
