<?php

namespace App\Tests\Domain\Rewind\FindStreaks;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Rewind\FindStreaks\FindStreaks;
use App\Domain\Rewind\FindStreaks\FindStreaksQueryHandler;
use App\Domain\Rewind\FindStreaks\FindStreaksResponse;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class FindStreaksQueryHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $clock = PausedClock::fromString('2025-01-11');

        foreach ($this->getSampleDates() as $index => $date) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($index))
                    ->withStartDateTime(SerializableDateTime::fromString($date))
                    ->withSportType(SportType::RIDE)
                    ->build(), []
            ));
        }

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('run'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-02-05'))
                ->withSportType(SportType::RUN)
                ->build(), []
        ));

        $findStreaksQueryHandler = new FindStreaksQueryHandler(
            $this->getConnection(),
            $clock
        );

        $this->assertEquals(
            new FindStreaksResponse(
                longestDayStreak: 5,
                currentDayStreak: 4,
                currentDayStreakStartDate: SerializableDateTime::fromString('2025-01-07'),
                longestWeekStreak: 2,
                currentWeekStreak: 2,
                currentWeekStreakStartDate: SerializableDateTime::fromString('2025-01-05'),
                longestMonthStreak: 9,
                currentMonthStreak: 9,
                currentMonthStreakStartDate: SerializableDateTime::fromString('2024-05-10'),
                longestDayStreakPeriod: DateRange::fromDates(
                    SerializableDateTime::fromString('2024-02-06'),
                    SerializableDateTime::fromString('2024-02-10'),
                ),
                longestWeekStreakPeriod: DateRange::fromDates(
                    SerializableDateTime::fromString('2024-12-30'),
                    SerializableDateTime::fromString('2025-01-10'),
                ),
                longestMonthStreakPeriod: DateRange::fromDates(
                    SerializableDateTime::fromString('2024-05-10'),
                    SerializableDateTime::fromString('2025-01-10'),
                ),
                longestBreak: DateRange::fromDates(
                    SerializableDateTime::fromString('2021-01-01'),
                    SerializableDateTime::fromString('2023-12-28'),
                ),
            ),
            $findStreaksQueryHandler->handle(
                new FindStreaks(
                    years: Years::all($clock->getCurrentDateTimeImmutable()),
                    restrictToSportTypes: SportTypes::fromArray([SportType::RIDE])
                )
            )
        );

        $clock = PausedClock::fromString('2030-01-01 14:00:00');
        $this->assertEquals(
            DateRange::fromDates(
                SerializableDateTime::fromString('2025-01-11'),
                SerializableDateTime::fromString('2029-12-31'),
            ),
            new FindStreaksQueryHandler($this->getConnection(), $clock)->handle(
                new FindStreaks(
                    years: Years::all($clock->getCurrentDateTimeImmutable()),
                    restrictToSportTypes: SportTypes::fromArray([SportType::RIDE])
                )
            )->getLongestBreak()
        );
    }

    public function testHandleFindStreaksWhenDataIsMissing(): void
    {
        $findStreaksQueryHandler = new FindStreaksQueryHandler(
            $this->getConnection(),
            $clock = PausedClock::fromString('2025-12-27')
        );

        $this->assertEquals(
            new FindStreaksResponse(
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
                longestBreak: null,
            ),
            $findStreaksQueryHandler->handle(
                new FindStreaks(
                    years: Years::all($clock->getCurrentDateTimeImmutable()),
                    restrictToSportTypes: null,
                )
            )
        );
    }

    private function getSampleDates(): array
    {
        return [
            // ───────────── 2025 ─────────────
            '2025-01-10',
            '2025-01-09',
            '2025-01-08',
            '2025-01-08', // duplicate day
            '2025-01-07',
            '2025-01-05', // gap (breaks day streak)
            '2025-01-03',
            '2025-01-02',
            // Same ISO week, earlier days
            '2024-12-31',
            '2024-12-30',
            // ───────────── Week gap ─────────────
            '2024-12-20',
            // ───────────── Month boundary ─────────────
            '2024-12-01',
            '2024-11-30',
            '2024-11-29',
            '2024-11-15',
            // ───────────── Long month streak ─────────────
            '2024-10-10',
            '2024-09-10',
            '2024-08-10',
            '2024-07-10',
            '2024-06-10',
            '2024-05-10',
            // ───────────── Broken month streak ─────────────
            '2024-03-15',
            // ───────────── Leap year coverage ─────────────
            '2024-02-29',
            '2024-02-28',
            '2024-02-27',
            // ───────────── More day streaks ─────────────
            '2024-02-10',
            '2024-02-09',
            '2024-02-08',
            '2024-02-07',
            '2024-02-06',
            // ───────────── Year boundary ─────────────
            '2023-12-31',
            '2023-12-30',
            '2023-12-29',
            // ───────────── ISO week 53 coverage ─────────────
            '2020-12-31',
            '2020-12-30',
            '2020-12-29',
            '2020-12-28',
            // ───────────── Earlier random data ─────────────
            '2019-11-15',
            '2019-10-10',
            '2019-09-09',
            '2018-06-01',
            '2018-05-31',
            '2018-05-30',
            '2017-01-01',
        ];
    }
}
