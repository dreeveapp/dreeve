<?php

namespace App\Tests\Domain\Calendar\FindMonthlyStats;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStatsQueryHandler;
use App\Domain\Calendar\Month;
use App\Domain\Gear\GearId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindMonthlyStatsQueryHandlerTest extends ContainerTestCase
{
    private FindMonthlyStatsQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withGearId(GearId::fromUnprefixed('5'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('8'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('9'))
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-10 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('10'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-15 00:00:00'))
                ->build(),
            []
        ));

        /** @var \App\Domain\Calendar\FindMonthlyStats\FindMonthlyStatsResponse $response */
        $response = $this->queryHandler->handle(new FindMonthlyStats());

        $month = Month::fromDate(SerializableDateTime::fromString('2024-01-03 00:00:00'));
        $this->assertEquals(
            ['numberOfActivities' => 5, 'distance' => Kilometer::from(50), 'elevation' => Meter::from(0), 'movingTime' => Seconds::from(50), 'calories' => 0],
            $response->getForMonth($month)
        );
        $this->assertNull($response->getForMonth(Month::fromDate(SerializableDateTime::fromString('2026-01-03 00:00:00'))));
        $this->assertEquals(
            ['numberOfActivities' => 4, 'distance' => Kilometer::from(40), 'elevation' => Meter::from(0), 'movingTime' => Seconds::from(40), 'calories' => 0],
            $response->getForMonthAndActivityType($month, ActivityType::RIDE)
        );
        $this->assertEquals(
            ['numberOfActivities' => 3, 'distance' => Kilometer::from(30), 'elevation' => Meter::from(0), 'movingTime' => Seconds::from(30), 'calories' => 0],
            $response->getForMonthAndSportType($month, SportType::RIDE)
        );

        $this->assertEquals(
            Month::fromDate(SerializableDateTime::fromString('2023-01-01 00:00:00')),
            $response->getFirstMonthFor(ActivityType::RIDE)
        );
        $this->assertEquals(
            Month::fromDate(SerializableDateTime::fromString('2025-01-01 00:00:00')),
            $response->getLastMonthFor(ActivityType::RIDE)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindMonthlyStatsQueryHandler($this->getConnection());
    }
}
