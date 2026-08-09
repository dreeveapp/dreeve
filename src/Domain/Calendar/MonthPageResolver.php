<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Page\PageResolver;
use App\Infrastructure\Http\Page\ResolvedPage;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Twig\Environment;

final readonly class MonthPageResolver implements PageResolver
{
    private const string BASE_PATH = 'month';

    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedPage
    {
        if (!preg_match('#^'.self::BASE_PATH.'/(\d{4}-(?:0[1-9]|1[0-2]))$#', $path, $matches)) {
            return null;
        }

        $month = Month::fromDate(SerializableDateTime::fromString($matches[1].'-01 00:00:00'));
        if (!$firstMonth = $this->queryBus->ask(new FindMonthlyStats())->getFirstMonth()) {
            return null;
        }

        // Months outside the range that actually holds activities would render an empty calendar, and every
        // one of them would claim its own cache entry.
        if ($month->isBefore($firstMonth) || $month->isAfter($this->currentMonth())) {
            return null;
        }

        return new ResolvedPage(
            path: self::BASE_PATH.'/'.$month->getId(),
            cacheability: $this->cacheabilityFor($month),
            render: fn (): string => $this->renderFor($month),
        );
    }

    private function cacheabilityFor(Month $month): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('%s.%s', self::BASE_PATH, $month->getId()),
            // The grid also renders the trailing days of the previous month and the leading days of the next
            // one, so an activity in either of those shows up on this page too.
            cacheTags: CacheTags::of(
                RootCacheTag::ACTIVITIES->forMonth($month->getPreviousMonth()),
                RootCacheTag::ACTIVITIES->forMonth($month),
                RootCacheTag::ACTIVITIES->forMonth($month->getNextMonth()),
            ),
        );
    }

    private function renderFor(Month $month): string
    {
        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());

        return $this->twig->load('html/calendar/month.html.twig')->render([
            'hasPreviousMonth' => $month->getId() !== $monthlyStats->getFirstMonth()?->getId(),
            'hasNextMonth' => $month->getId() !== $this->currentMonth()->getId(),
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

    private function currentMonth(): Month
    {
        return Month::fromDate($this->clock->getCurrentDateTimeImmutable());
    }
}
