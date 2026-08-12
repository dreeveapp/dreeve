<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDate;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Twig\Environment;

final readonly class MonthFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'month';

    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/(\d{4}-(?:0[1-9]|1[0-2]))$#', $path, $matches)) {
            return null;
        }

        $month = Month::fromDate(SerializableDateTime::fromString($matches[1].'-01 00:00:00'));
        try {
            $firstMonth = Month::fromDate($this->queryBus->ask(new FindFirstActivityStartDate())->getStartDate());
        } catch (\RuntimeException) {
            return null;
        }

        if ($month->isBefore($firstMonth) || $month->isAfter($this->currentMonth())) {
            return null;
        }

        return new ResolvedFragment(
            path: self::BASE_PATH.'/'.$month->getId(),
            cacheability: $this->cacheabilityFor($month),
            render: fn (): string => $this->renderFor($month),
        );
    }

    private function cacheabilityFor(Month $month): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('%s.%s', self::BASE_PATH, $month->getId()),
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
