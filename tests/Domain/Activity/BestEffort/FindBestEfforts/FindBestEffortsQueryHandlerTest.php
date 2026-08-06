<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\BestEffort\FindBestEfforts;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\BestEffort\FindBestEfforts\FindBestEfforts;
use App\Domain\Activity\BestEffort\FindBestEfforts\FindBestEffortsQueryHandler;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\BestEffort\ActivityBestEffortBuilder;

class FindBestEffortsQueryHandlerTest extends ContainerTestCase
{
    private FindBestEffortsQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->addActivity('1', '2023-01-01 10:00:00', SportType::RIDE);
        $this->addBestEffort('1', SportType::RIDE, 10000, 1800);
        $this->addActivity('2', '2023-01-02 10:00:00', SportType::MOUNTAIN_BIKE_RIDE);
        $this->addBestEffort('2', SportType::MOUNTAIN_BIKE_RIDE, 10000, 1500);
        // A sport type that does not support best efforts, it should be ignored.
        $this->addActivity('3', '2023-01-03 10:00:00', SportType::WALK);
        $this->addBestEffort('3', SportType::WALK, 10000, 900);

        $response = $this->queryHandler->handle(new FindBestEfforts());

        // Fastest first.
        $this->assertEquals(
            [
                ['activity-2', 1500],
                ['activity-1', 1800],
            ],
            $response->getBestEfforts()->map(fn ($bestEffort): array => [
                (string) $bestEffort->getActivityId(),
                $bestEffort->getTimeInSeconds(),
            ])
        );

        $this->assertEquals(
            [
                'activity-2' => SerializableDateTime::fromString('2023-01-02 10:00:00'),
                'activity-1' => SerializableDateTime::fromString('2023-01-01 10:00:00'),
            ],
            $response->getStartDateTimePerActivity()
        );
    }

    public function testHandleWithEqualTimes(): void
    {
        // Identical times, the oldest activity should be ranked first.
        $this->addActivity('1', '2023-01-02 10:00:00', SportType::RIDE);
        $this->addBestEffort('1', SportType::RIDE, 10000, 1800);
        $this->addActivity('2', '2023-01-01 10:00:00', SportType::RIDE);
        $this->addBestEffort('2', SportType::RIDE, 10000, 1800);

        $response = $this->queryHandler->handle(new FindBestEfforts());

        $this->assertEquals(
            ['activity-2', 'activity-1'],
            $response->getBestEfforts()->map(fn ($bestEffort): string => (string) $bestEffort->getActivityId())
        );
    }

    public function testHandleWithoutBestEfforts(): void
    {
        $response = $this->queryHandler->handle(new FindBestEfforts());

        $this->assertTrue($response->getBestEfforts()->isEmpty());
        $this->assertEquals([], $response->getStartDateTimePerActivity());
    }

    private function addActivity(string $activityId, string $startDateTime, SportType $sportType): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->withSportType($sportType)
                ->build(),
            []
        ));
    }

    private function addBestEffort(string $activityId, SportType $sportType, int $distanceInMeter, int $timeInSeconds): void
    {
        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withSportType($sportType)
                ->withDistanceInMeter(Meter::from($distanceInMeter))
                ->withTimeInSeconds($timeInSeconds)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindBestEffortsQueryHandler($this->getConnection());
    }
}
