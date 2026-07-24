<?php

namespace App\Tests\Domain\Rewind\FindTotalsPerMonth;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Rewind\FindTotalsPerMonth\FindTotalsPerMonth;
use App\Domain\Rewind\FindTotalsPerMonth\FindTotalsPerMonthQueryHandler;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindTotalsPerMonthQueryHandlerTest extends ContainerTestCase
{
    private FindTotalsPerMonthQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withGearId(GearId::fromUnprefixed('5'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('8'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-03 00:00:00'))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(100)
                ->build(),
            []
        ));

        /** @var \App\Domain\Rewind\FindTotalsPerMonth\FindTotalsPerMonthResponse $response */
        $response = $this->queryHandler->handle(new FindTotalsPerMonth(Years::fromArray([Year::fromInt(2024)])));

        $this->assertEquals(
            [
                [1, SportType::RIDE, Kilometer::from(30)],
                [3, SportType::RIDE, Kilometer::from(10)],
            ],
            $response->getDistancePerMonth(),
        );
        $this->assertEquals(
            [
                [1, SportType::RIDE, Meter::from(300)],
                [3, SportType::RIDE, Meter::from(100)],
            ],
            $response->getElevationPerMonth(),
        );
        $this->assertEquals(
            [
                [1, SportType::RIDE, 300],
                [3, SportType::RIDE, 100],
            ],
            $response->getMovingTimePerMonth(),
        );

        $this->assertEquals(Kilometer::from(40), $response->getTotalDistance());
        $this->assertEquals(Meter::from(400), $response->getTotalElevation());
        $this->assertEquals(400, $response->getTotalMovingTime());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindTotalsPerMonthQueryHandler($this->getConnection());
    }
}
