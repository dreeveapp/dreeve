<?php

declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDate;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\Month;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class MonthRequestHandler
{
    private const string BASE_PATH = 'monthly-stats';

    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/monthly-stats/{month}', name: 'monthly_stats_month', requirements: ['month' => '\d{4}-(?:0[1-9]|1[0-2])'], methods: ['GET'], priority: 3)]
    public function handle(string $month): Response
    {
        $month = Month::fromDate(SerializableDateTime::fromString($month.'-01 00:00:00'));
        try {
            $firstMonth = Month::fromDate($this->queryBus->ask(new FindFirstActivityStartDate())->getStartDate());
        } catch (\RuntimeException) {
            throw new NotFoundHttpException('Not found');
        }

        if ($month->isBefore($firstMonth) || $month->isAfter(Month::fromDate($this->clock->getCurrentDateTimeImmutable()))) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s', self::BASE_PATH, $month->getId()),
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITIES->forMonth($month->getPreviousMonth()),
                    RootCacheTag::ACTIVITIES->forMonth($month),
                    RootCacheTag::ACTIVITIES->forMonth($month->getNextMonth()),
                ),
            ),
            render: fn (): string => $this->renderFor($month),
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

    private function renderFor(Month $month): string
    {
        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());

        return $this->twig->load('html/calendar/month.html.twig')->render([
            'hasPreviousMonth' => $month->getId() !== $monthlyStats->getFirstMonth()?->getId(),
            'hasNextMonth' => $month->getId() !== Month::fromDate($this->clock->getCurrentDateTimeImmutable())->getId(),
            'statistics' => $monthlyStats->getForMonth($month),
            'calendar' => Calendar::create(
                month: $month,
                activities: $this->activityRepository->findByDateRange(
                    from: $month->getPreviousMonth()->getFirstDay(),
                    till: $month->getNextMonth()->getNextMonth()->getFirstDay(),
                ),
            ),
        ]);
    }
}
