<?php

declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class MonthlyStatsRequestHandler
{
    public function __construct(
        private SportTypeRepository $sportTypeRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/monthly-stats', name: 'monthly_stats', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: 'monthly-stats',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::MONTHLY_STATS,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());

        $firstMonth = $monthlyStats->getFirstMonth();
        $allMonths = $firstMonth instanceof Month ? Months::create(
            startDate: $firstMonth->getFirstDay(),
            endDate: $this->clock->getCurrentDateTimeImmutable()
        ) : Months::empty();

        return $this->twig->load('html/calendar/monthly-stats.html.twig')->render([
            'monthlyStatistics' => $monthlyStats,
            'months' => $allMonths->reverse(),
            'sportTypes' => $this->sportTypeRepository->findAll(),
        ]);
    }
}
