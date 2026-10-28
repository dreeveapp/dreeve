<?php

declare(strict_types=1);

namespace App\Controller\Page\Calendar;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\Year;
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
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/monthly-stats', name: 'monthly_stats', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'monthly-stats',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::MONTHLY_STATS,
        );
    }

    private function renderFor(): string
    {
        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());
        $now = $this->clock->getCurrentDateTimeImmutable();
        $currentMonth = Month::fromDate($now);
        $today = $now->getDayOfTheMonth();
        $sportTypes = $this->sportTypeRepository->findAll();

        $firstMonth = $monthlyStats->getFirstMonth();
        $allMonths = $firstMonth instanceof Month ? Months::create(
            startDate: $firstMonth->getFirstDay(),
            endDate: $now
        ) : Months::empty();

        $years = [];
        foreach ($allMonths->reverse() as $month) {
            $stats = $monthlyStats->getForMonth($month);
            if (null === $stats) {
                continue;
            }

            $year = Year::fromInt($month->getYear());
            $years[$year->toInt()] ??= [
                'year' => $year,
                'totals' => $monthlyStats->getForYear($year),
                'isCurrent' => $year->toInt() === $now->getYear(),
                'months' => [],
            ];

            $statsPerSportType = [];
            foreach ($sportTypes as $sportType) {
                $statsForSportType = $monthlyStats->getForMonthAndSportType($month, $sportType);
                if (0 === $statsForSportType['numberOfActivities']) {
                    continue;
                }
                $statsPerSportType[] = [
                    'sportType' => $sportType,
                    'stats' => $statsForSportType,
                    'shareOfMovingTime' => $stats['movingTime']->toInt() > 0
                        ? (int) round($statsForSportType['movingTime']->toInt() / $stats['movingTime']->toInt() * 100)
                        : 0,
                ];
            }

            $activeDays = $monthlyStats->getActiveDaysFor($month);
            $isCurrentMonth = $month->getId() === $currentMonth->getId();
            $days = [];
            for ($dayNumber = 1; $dayNumber <= $month->getNumberOfDays(); ++$dayNumber) {
                $days[] = [
                    'dayNumber' => $dayNumber,
                    'state' => match (true) {
                        in_array($dayNumber, $activeDays, true) => 'active',
                        $isCurrentMonth && $dayNumber > $today => 'upcoming',
                        default => 'rest',
                    },
                ];
            }

            $years[$year->toInt()]['months'][] = [
                'month' => $month,
                'stats' => $stats,
                'sportTypes' => $statsPerSportType,
                'days' => $days,
                'numberOfActiveDays' => count($activeDays),
            ];
        }

        return $this->twig->load('html/calendar/monthly-stats.html.twig')->render([
            'years' => array_values($years),
        ]);
    }
}
