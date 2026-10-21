<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindStreaks;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindStreaksQueryHandler implements QueryHandler
{
    private const string FIRST_DAY_OF_THE_WEEK = 'monday this week';

    public function __construct(
        private Connection $connection,
        private Clock $clock,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindStreaks);

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select("strftime('%Y-%m-%d', startDateTime) AS day")
            ->from('activity')
            ->andWhere("strftime('%Y', startDateTime) IN (:years)")
            ->groupBy('day')
            ->orderBy('day', 'DESC')
            ->setParameter(
                key: 'years',
                value: array_map(strval(...), $query->getYears()->toArray()),
                type: ArrayParameterType::STRING
            );

        $restrictToSportTypes = $query->getRestrictToSportTypes();
        if (!is_null($restrictToSportTypes) && !$restrictToSportTypes->isEmpty()) {
            $queryBuilder
                ->andWhere('sportType IN (:sportTypes)')
                ->setParameter(
                    key: 'sportTypes',
                    value: array_map(fn (SportType $sportType) => $sportType->value, $restrictToSportTypes->toArray()),
                    type: ArrayParameterType::STRING
                );
        }

        /** @var SerializableDateTime[] $days */
        $days = array_map(
            SerializableDateTime::fromString(...),
            $queryBuilder->fetchFirstColumn()
        );

        return $this->computeStreaks($days);
    }

    /**
     * @param SerializableDateTime[] $days
     */
    private function computeStreaks(array $days): FindStreaksResponse
    {
        if ([] === $days) {
            return new FindStreaksResponse(
                longestDayStreak: 0,
                currentDayStreak: 0,
                currentDayStreakStartDate: null,
                longestWeekStreak: 0,
                currentWeekStreak: 0,
                currentWeekStreakStartDate: null,
                longestMonthStreak: 0,
                currentMonthStreak: 0,
                currentMonthStreakStartDate: null,
                longestDayStreakPeriod: null,
                longestWeekStreakPeriod: null,
                longestMonthStreakPeriod: null,
            );
        }

        $today = $this->clock->getCurrentDateTimeImmutable();

        $longestDayStreak = $runningDayStreak = 1;
        $longestWeekStreak = $runningWeekStreak = 1;
        $longestMonthStreak = $runningMonthStreak = 1;

        $previousDay = array_first($days);
        $runningDayStreakEnd = $runningWeekStreakEnd = $runningMonthStreakEnd = $previousDay;
        $runningDayStreakIsLongest = $runningWeekStreakIsLongest = $runningMonthStreakIsLongest = true;
        $longestDayStreakPeriod = $longestWeekStreakPeriod = $longestMonthStreakPeriod = DateRange::fromDates($previousDay, $previousDay);

        $keepTrackOfCurrentDayStreak = $today->diff($previousDay)->days < 2;
        $currentDayStreak = $keepTrackOfCurrentDayStreak ? 1 : 0;
        $currentDayStreakStartDate = $keepTrackOfCurrentDayStreak ? $previousDay : null;

        $keepTrackOfCurrentWeekStreak = $today->diff($previousDay)->days < 7;
        $currentWeekStreak = $keepTrackOfCurrentWeekStreak ? 1 : 0;
        $currentWeekStreakStartDate = $keepTrackOfCurrentWeekStreak ? $previousDay : null;

        $monthDiff =
            (($today->getYear() - $previousDay->getYear()) * 12)
            + ($today->getMonthWithoutLeadingZero() - $previousDay->getMonthWithoutLeadingZero());
        $keepTrackOfCurrentMonthStreak = $monthDiff <= 1;
        $currentMonthStreak = $keepTrackOfCurrentMonthStreak ? 1 : 0;
        $currentMonthStreakStartDate = $keepTrackOfCurrentMonthStreak ? $previousDay : null;
        $counter = count($days);

        for ($i = 1; $i < $counter; ++$i) {
            $currentDay = $days[$i];
            $diffInDays = $previousDay->diff($currentDay)->days;

            if (1 === $diffInDays) {
                ++$runningDayStreak;
                if ($keepTrackOfCurrentDayStreak) {
                    ++$currentDayStreak;
                    $currentDayStreakStartDate = $currentDay;
                }
                if ($runningDayStreak > $longestDayStreak) {
                    $longestDayStreak = $runningDayStreak;
                    $runningDayStreakIsLongest = true;
                }
            } else {
                $runningDayStreak = 1;
                $runningDayStreakEnd = $currentDay;
                $runningDayStreakIsLongest = false;
                $keepTrackOfCurrentDayStreak = false;
            }
            if ($runningDayStreakIsLongest) {
                $longestDayStreakPeriod = DateRange::fromDates($currentDay, $runningDayStreakEnd);
            }

            $diffInWeeks = (int) floor($previousDay->modify(self::FIRST_DAY_OF_THE_WEEK)
                    ->diff($currentDay->modify(self::FIRST_DAY_OF_THE_WEEK))->days / 7);
            // Skip duplicate weeks (multiple activities in same week).
            if ($diffInWeeks > 0) {
                if (1 === $diffInWeeks) {
                    ++$runningWeekStreak;
                    if ($keepTrackOfCurrentWeekStreak) {
                        ++$currentWeekStreak;
                        $currentWeekStreakStartDate = $currentDay;
                    }
                    if ($runningWeekStreak > $longestWeekStreak) {
                        $longestWeekStreak = $runningWeekStreak;
                        $runningWeekStreakIsLongest = true;
                    }
                } else {
                    $runningWeekStreak = 1;
                    $runningWeekStreakEnd = $currentDay;
                    $runningWeekStreakIsLongest = false;
                    $keepTrackOfCurrentWeekStreak = false;
                }
            }
            if ($runningWeekStreakIsLongest) {
                $longestWeekStreakPeriod = DateRange::fromDates($currentDay, $runningWeekStreakEnd);
            }

            $diffInMonths = (($previousDay->getYear() - $currentDay->getYear()) * 12)
                + ($previousDay->getMonthWithoutLeadingZero() - $currentDay->getMonthWithoutLeadingZero());
            // Skip duplicate months (multiple activities in same month).
            if ($diffInMonths > 0) {
                if (1 === $diffInMonths) {
                    ++$runningMonthStreak;
                    if ($keepTrackOfCurrentMonthStreak) {
                        ++$currentMonthStreak;
                        $currentMonthStreakStartDate = $currentDay;
                    }
                    if ($runningMonthStreak > $longestMonthStreak) {
                        $longestMonthStreak = $runningMonthStreak;
                        $runningMonthStreakIsLongest = true;
                    }
                } else {
                    $runningMonthStreak = 1;
                    $runningMonthStreakEnd = $currentDay;
                    $runningMonthStreakIsLongest = false;
                    $keepTrackOfCurrentMonthStreak = false;
                }
            }
            if ($runningMonthStreakIsLongest) {
                $longestMonthStreakPeriod = DateRange::fromDates($currentDay, $runningMonthStreakEnd);
            }

            $previousDay = $currentDay;
        }

        return new FindStreaksResponse(
            longestDayStreak: $longestDayStreak,
            currentDayStreak: $currentDayStreak,
            currentDayStreakStartDate: $currentDayStreakStartDate,
            longestWeekStreak: $longestWeekStreak,
            currentWeekStreak: $currentWeekStreak,
            currentWeekStreakStartDate: $currentWeekStreakStartDate,
            longestMonthStreak: $longestMonthStreak,
            currentMonthStreak: $currentMonthStreak,
            currentMonthStreakStartDate: $currentMonthStreakStartDate,
            longestDayStreakPeriod: $longestDayStreakPeriod,
            longestWeekStreakPeriod: $longestWeekStreakPeriod,
            longestMonthStreakPeriod: $longestMonthStreakPeriod,
        );
    }
}
