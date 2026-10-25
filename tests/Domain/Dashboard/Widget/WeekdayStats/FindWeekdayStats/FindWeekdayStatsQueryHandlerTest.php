<?php

namespace App\Tests\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats\FindWeekdayStats;
use App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats\FindWeekdayStatsQueryHandler;
use App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats\FindWeekdayStatsResponse;
use App\Domain\Dashboard\Widget\WeekdayStats\Weekday;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindWeekdayStatsQueryHandlerTest extends ContainerTestCase
{
    private FindWeekdayStatsQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $activities = [
            ['2025-01-06 08:00:00', SportType::RIDE, 10, 100, 3600],
            ['2025-01-05 23:30:00', SportType::RIDE, 20, 0, 1800],
            ['2025-01-13 07:00:00', SportType::RUN, 5, 50, 1200],
            ['2025-01-08 18:00:00', SportType::RUN, 6, 20, 600],
        ];
        foreach ($activities as $index => [$startDateTime, $sportType, $distance, $elevation, $movingTime]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $index))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->withSportType($sportType)
                    ->withDistance(Kilometer::from($distance))
                    ->withElevation(Meter::from($elevation))
                    ->withMovingTimeInSeconds($movingTime)
                    ->build(),
                []
            ));
        }

        /** @var FindWeekdayStatsResponse $response */
        $response = $this->queryHandler->handle(new FindWeekdayStats());

        $this->assertSame(
            [
                Weekday::MONDAY->name => [2, 15.0, 7.5, 150.0, 4800, 66.67],
                Weekday::TUESDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::WEDNESDAY->name => [1, 6.0, 6.0, 20.0, 600, 8.33],
                Weekday::THURSDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::FRIDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::SATURDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::SUNDAY->name => [1, 20.0, 20.0, 0.0, 1800, 25.0],
            ],
            $this->toAssertable($response->getStatsPerWeekday(null)),
        );
        $this->assertSame(
            [
                Weekday::MONDAY->name => [1, 10.0, 10.0, 100.0, 3600, 66.67],
                Weekday::TUESDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::WEDNESDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::THURSDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::FRIDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::SATURDAY->name => [0, 0.0, 0.0, 0.0, 0, 0],
                Weekday::SUNDAY->name => [1, 20.0, 20.0, 0.0, 1800, 33.33],
            ],
            $this->toAssertable($response->getStatsPerWeekday(ActivityType::RIDE)),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $statsPerWeekday
     *
     * @return array<string, array<int, mixed>>
     */
    private function toAssertable(array $statsPerWeekday): array
    {
        $assertable = [];
        foreach ($statsPerWeekday as $stats) {
            $assertable[$stats['weekday']->name] = [
                $stats['numberOfActivities'],
                $stats['distance']->toFloat(),
                $stats['averageDistance']->toFloat(),
                $stats['elevation']->toFloat(),
                $stats['movingTime']->toInt(),
                $stats['percentage'],
            ];
        }

        return $assertable;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindWeekdayStatsQueryHandler($this->getConnection());
    }
}
