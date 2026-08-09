<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStatsResponse;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Page\PageWithParameters;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Twig\Environment;

final readonly class MonthPage implements PageWithParameters
{
    private const string BASE_PATH = 'month';

    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
        private string $monthId = '',
    ) {
    }

    public function resolve(string $path): ?self
    {
        if (!preg_match('#^'.self::BASE_PATH.'/(\d{4}-(?:0[1-9]|1[0-2]))$#', $path, $matches)) {
            return null;
        }

        $month = self::toMonth($matches[1]);
        if (!$firstMonth = $this->queryBus->ask(new FindMonthlyStats())->getFirstMonth()) {
            return null;
        }

        if ($month->isBefore($firstMonth) || $month->isAfter($this->currentMonth())) {
            return null;
        }

        return clone ($this, ['monthId' => $month->getId()]);
    }

    public function getPath(): string
    {
        return self::BASE_PATH.'/'.$this->getMonth()->getId();
    }

    public function getCacheability(): Cacheability
    {
        $month = $this->getMonth();

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

    public function render(): string
    {
        $month = $this->getMonth();
        /** @var FindMonthlyStatsResponse $monthlyStats */
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

    private function getMonth(): Month
    {
        return '' === $this->monthId ? $this->currentMonth() : self::toMonth($this->monthId);
    }

    private static function toMonth(string $monthId): Month
    {
        return Month::fromDate(SerializableDateTime::fromString($monthId.'-01 00:00:00'));
    }

    private function currentMonth(): Month
    {
        return Month::fromDate($this->clock->getCurrentDateTimeImmutable());
    }
}
