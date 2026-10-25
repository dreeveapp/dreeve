<?php

namespace App\Tests\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\Widget\DaytimeStats\Daytime;
use App\Domain\Dashboard\Widget\DaytimeStats\DaytimeStartHours;
use App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats\FindDaytimeStats;
use App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats\FindDaytimeStatsQueryHandler;
use App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats\FindDaytimeStatsResponse;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindDaytimeStatsQueryHandlerTest extends ContainerTestCase
{
    private FindDaytimeStatsQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $activities = [
            ['2025-01-01 00:30:00', SportType::RIDE, 10, 100, 3600],
            ['2025-01-01 04:00:00', SportType::RIDE, 20, 0, 1800],
            ['2025-01-01 03:59:00', SportType::RUN, 6, 20, 2000],
            ['2025-01-01 23:00:00', SportType::RUN, 5, 50, 1500],
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

        /** @var FindDaytimeStatsResponse $response */
        $response = $this->queryHandler->handle(new FindDaytimeStats());
        $startHours = DaytimeStartHours::create(morning: 4, afternoon: 11, evening: 17, night: 1);

        $numberOfActivitiesPerHour = $response->getNumberOfActivitiesPerHour($startHours, null);
        $this->assertSame([...range(4, 23), ...range(0, 3)], array_keys($numberOfActivitiesPerHour));
        $this->assertSame([4 => 1, 23 => 1, 0 => 1, 3 => 1], array_filter($numberOfActivitiesPerHour));
        $this->assertSame([23 => 1, 3 => 1], array_filter($response->getNumberOfActivitiesPerHour($startHours, ActivityType::RUN)));

        $this->assertSame(
            [
                Daytime::MORNING->value => [4, 11, 1, 20.0, 20.0, 0.0, 1800],
                Daytime::AFTERNOON->value => [11, 17, 0, 0.0, 0.0, 0.0, 0],
                Daytime::EVENING->value => [17, 1, 2, 15.0, 7.5, 150.0, 5100],
                Daytime::NIGHT->value => [1, 4, 1, 6.0, 6.0, 20.0, 2000],
            ],
            $this->toAssertable($response->getStatsPerDaytime($startHours, null)),
        );
        $this->assertSame(
            [
                Daytime::MORNING->value => [4, 11, 1, 20.0, 20.0, 0.0, 1800],
                Daytime::AFTERNOON->value => [11, 17, 0, 0.0, 0.0, 0.0, 0],
                Daytime::EVENING->value => [17, 1, 1, 10.0, 10.0, 100.0, 3600],
                Daytime::NIGHT->value => [1, 4, 0, 0.0, 0.0, 0.0, 0],
            ],
            $this->toAssertable($response->getStatsPerDaytime($startHours, ActivityType::RIDE)),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $statsPerDaytime
     *
     * @return array<string, array<int, mixed>>
     */
    private function toAssertable(array $statsPerDaytime): array
    {
        return array_map(
            static fn (array $stats): array => [
                $stats['startHour'],
                $stats['endHour'],
                $stats['numberOfActivities'],
                $stats['distance']->toFloat(),
                $stats['averageDistance']->toFloat(),
                $stats['elevation']->toFloat(),
                $stats['movingTime']->toInt(),
            ],
            $statsPerDaytime,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindDaytimeStatsQueryHandler($this->getConnection());
    }
}
