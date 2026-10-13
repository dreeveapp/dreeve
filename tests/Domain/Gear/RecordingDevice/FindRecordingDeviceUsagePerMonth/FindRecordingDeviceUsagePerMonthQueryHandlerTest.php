<?php

namespace App\Tests\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Calendar\Month;
use App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonth;
use App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonthQueryHandler;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindRecordingDeviceUsagePerMonthQueryHandlerTest extends ContainerTestCase
{
    private FindRecordingDeviceUsagePerMonthQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 540')
                ->withSportType(SportType::RIDE)
                ->withDistance(Kilometer::from(10))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-05 08:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withDeviceName('Garmin Edge 540')
                ->withSportType(SportType::GRAVEL_RIDE)
                ->withDistance(Kilometer::from(20))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-20 09:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withDeviceName('Garmin Edge 540')
                ->withSportType(SportType::RIDE)
                ->withDistance(Kilometer::from(40))
                ->withStartDateTime(SerializableDateTime::fromString('2023-03-02 10:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withDeviceName('Polar Vantage M')
                ->withSportType(SportType::RUN)
                ->withDistance(Kilometer::from(5))
                ->withStartDateTime(SerializableDateTime::fromString('2023-02-10 07:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('5'))
                ->withoutDeviceName()
                ->withSportType(SportType::WALK)
                ->withDistance(Kilometer::from(3))
                ->withStartDateTime(SerializableDateTime::fromString('2023-02-11 07:00:00'))
                ->build(),
            []
        ));

        /** @var \App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonthResponse $response */
        $response = $this->queryHandler->handle(new FindRecordingDeviceUsagePerMonth());

        $january = Month::fromDate(SerializableDateTime::fromString('2023-01-01'));
        $february = Month::fromDate(SerializableDateTime::fromString('2023-02-01'));
        $march = Month::fromDate(SerializableDateTime::fromString('2023-03-01'));

        $usage = $response->getFor('Garmin Edge 540');
        $this->assertEquals(SerializableDateTime::fromString('2023-01-05 08:00:00'), $usage->getFirstUsedOn());
        $this->assertEquals(SerializableDateTime::fromString('2023-03-02 10:00:00'), $usage->getLastUsedOn());
        $this->assertEquals(SportTypes::fromArray([SportType::RIDE, SportType::GRAVEL_RIDE]), $usage->getSportTypes());
        $this->assertEquals(Kilometer::from(30), $usage->getDistanceFor($january));
        $this->assertEquals(Kilometer::zero(), $usage->getDistanceFor($february));
        $this->assertEquals(Kilometer::from(40), $usage->getDistanceFor($march));

        $usage = $response->getFor('Polar Vantage M');
        $this->assertEquals(SerializableDateTime::fromString('2023-02-10 07:00:00'), $usage->getFirstUsedOn());
        $this->assertEquals(SerializableDateTime::fromString('2023-02-10 07:00:00'), $usage->getLastUsedOn());
        $this->assertEquals(SportTypes::fromArray([SportType::RUN]), $usage->getSportTypes());
        $this->assertEquals(Kilometer::from(5), $usage->getDistanceFor($february));
    }

    public function testItShouldThrowWhenDeviceHasNoUsage(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withoutDeviceName()
                ->build(),
            []
        ));

        /** @var \App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonthResponse $response */
        $response = $this->queryHandler->handle(new FindRecordingDeviceUsagePerMonth());

        $this->expectExceptionObject(new \RuntimeException('No usage found for recording device "Garmin Edge 540"'));
        $response->getFor('Garmin Edge 540');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindRecordingDeviceUsagePerMonthQueryHandler($this->getConnection());
    }
}
