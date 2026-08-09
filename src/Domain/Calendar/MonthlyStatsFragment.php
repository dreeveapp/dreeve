<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Time\Clock\Clock;
use Twig\Environment;

final readonly class MonthlyStatsFragment implements Fragment
{
    public function __construct(
        private SportTypeRepository $sportTypeRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'monthly-stats';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
        );
    }

    public function render(): string
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
