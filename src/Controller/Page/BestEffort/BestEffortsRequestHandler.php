<?php

declare(strict_types=1);

namespace App\Controller\Page\BestEffort;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\BestEffort\BestEffortChart;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Theme\Theme;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class BestEffortsRequestHandler
{
    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private ActivityRepository $activityRepository,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
        private Theme $theme,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/best-efforts', name: 'best_efforts', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'best-efforts',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::BEST_EFFORTS,
        );
    }

    private function renderFor(): string
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();
        $bestEffortsCharts = [];

        foreach ($bestEfforts->getActivityTypes() as $activityType) {
            foreach (BestEffortPeriod::cases() as $bestEffortPeriod) {
                $bestEffortsCharts[$activityType->value][$bestEffortPeriod->value] = Json::encode(
                    BestEffortChart::create(
                        activityType: $activityType,
                        period: $bestEffortPeriod,
                        bestEfforts: $bestEfforts,
                        translator: $this->translator,
                        theme: $this->theme,
                    )->build()
                );
            }
        }

        return $this->twig->load('html/best-efforts/best-efforts.html.twig')->render([
            'bestEffortsCharts' => $bestEffortsCharts,
            'bestEfforts' => $bestEfforts,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($bestEfforts->getActivityIds())->keyByActivityId(),
        ]);
    }
}
