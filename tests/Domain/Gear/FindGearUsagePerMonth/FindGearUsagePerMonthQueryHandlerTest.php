<?php

namespace App\Tests\Domain\Gear\FindGearUsagePerMonth;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Calendar\Month;
use App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonth;
use App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonthQueryHandler;
use App\Domain\Gear\GearId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindGearUsagePerMonthQueryHandlerTest extends ContainerTestCase
{
    private FindGearUsagePerMonthQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistance(Kilometer::from(10))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-05 08:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withSportType(SportType::GRAVEL_RIDE)
                ->withDistance(Kilometer::from(20))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-20 09:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistance(Kilometer::from(40))
                ->withStartDateTime(SerializableDateTime::fromString('2023-03-02 10:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withSportType(SportType::RUN)
                ->withDistance(Kilometer::from(5))
                ->withStartDateTime(SerializableDateTime::fromString('2023-02-10 07:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('5'))
                ->withoutGearId()
                ->withSportType(SportType::WALK)
                ->withDistance(Kilometer::from(3))
                ->withStartDateTime(SerializableDateTime::fromString('2023-02-11 07:00:00'))
                ->build(),
            []
        ));

        /** @var \App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonthResponse $response */
        $response = $this->queryHandler->handle(new FindGearUsagePerMonth());

        $january = Month::fromDate(SerializableDateTime::fromString('2023-01-01'));
        $february = Month::fromDate(SerializableDateTime::fromString('2023-02-01'));
        $march = Month::fromDate(SerializableDateTime::fromString('2023-03-01'));

        $usage = $response->getFor(GearId::fromUnprefixed('1'));
        $this->assertEquals(SerializableDateTime::fromString('2023-01-05 08:00:00'), $usage->getFirstUsedOn());
        $this->assertEquals(SerializableDateTime::fromString('2023-03-02 10:00:00'), $usage->getLastUsedOn());
        $this->assertEquals(SportTypes::fromArray([SportType::RIDE, SportType::GRAVEL_RIDE]), $usage->getSportTypes());
        $this->assertEquals(Kilometer::from(30), $usage->getDistanceFor($january));
        $this->assertEquals(Kilometer::zero(), $usage->getDistanceFor($february));
        $this->assertEquals(Kilometer::from(40), $usage->getDistanceFor($march));

        $usage = $response->getFor(GearId::fromUnprefixed('2'));
        $this->assertEquals(SerializableDateTime::fromString('2023-02-10 07:00:00'), $usage->getFirstUsedOn());
        $this->assertEquals(SerializableDateTime::fromString('2023-02-10 07:00:00'), $usage->getLastUsedOn());
        $this->assertEquals(SportTypes::fromArray([SportType::RUN]), $usage->getSportTypes());
        $this->assertEquals(Kilometer::from(5), $usage->getDistanceFor($february));

        $usage = $response->getFor(GearId::none());
        $this->assertEquals(SportTypes::fromArray([SportType::WALK]), $usage->getSportTypes());
        $this->assertEquals(Kilometer::from(3), $usage->getDistanceFor($february));
    }

    public function testItShouldThrowWhenGearHasNoUsage(): void
    {
        /** @var \App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonthResponse $response */
        $response = $this->queryHandler->handle(new FindGearUsagePerMonth());

        $this->expectExceptionObject(new \RuntimeException('No usage found for gear "gear-1"'));
        $response->getFor(GearId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindGearUsagePerMonthQueryHandler($this->getConnection());
    }
}
