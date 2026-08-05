<?php

namespace App\Tests\Domain\Activity\Eddington\FindDistancePerDay;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Eddington\FindDistancePerDay\FindDistancePerDay;
use App\Domain\Activity\Eddington\FindDistancePerDay\FindDistancePerDayQueryHandler;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindDistancePerDayQueryHandlerTest extends ContainerTestCase
{
    private FindDistancePerDayQueryHandler $queryHandler;

    public function testHandle(): void
    {
        // Two activities on the same day, they should be summed.
        $this->addActivity('1', '2023-01-02 09:00:00', SportType::RIDE, 2.5);
        $this->addActivity('2', '2023-01-02 18:00:00', SportType::RIDE, 4);
        // A different day, added out of chronological order.
        $this->addActivity('3', '2023-01-01 10:00:00', SportType::MOUNTAIN_BIKE_RIDE, 10);
        // A sport type that is not included, it should be ignored.
        $this->addActivity('4', '2023-01-03 10:00:00', SportType::RUN, 5);

        $response = $this->queryHandler->handle(new FindDistancePerDay(SportTypes::fromArray([
            SportType::RIDE,
            SportType::MOUNTAIN_BIKE_RIDE,
        ])));

        $this->assertEquals(
            [
                '2023-01-01' => Meter::from(10000),
                '2023-01-02' => Meter::from(6500),
            ],
            $response->getDistancePerDay()
        );
    }

    public function testHandleWithoutMatchingActivities(): void
    {
        $this->addActivity('1', '2023-01-01 10:00:00', SportType::RUN, 10);

        $response = $this->queryHandler->handle(new FindDistancePerDay(SportTypes::fromArray([SportType::RIDE])));

        $this->assertEquals([], $response->getDistancePerDay());
    }

    private function addActivity(string $activityId, string $startDateTime, SportType $sportType, float $distanceInKm): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->withSportType($sportType)
                ->withDistance(Kilometer::from($distanceInKm))
                ->build(),
            []
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindDistancePerDayQueryHandler($this->getConnection());
    }
}
