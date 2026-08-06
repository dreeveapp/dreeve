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
    private ActivityRepository $activityRepository;
    private ActivityBestEffortRepository $activityBestEffortRepository;

    public function testHandle(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-02 10:00:00'))
                ->withSportType(SportType::MOUNTAIN_BIKE_RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::MOUNTAIN_BIKE_RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1500)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-03 10:00:00'))
                ->withSportType(SportType::WALK)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withSportType(SportType::WALK)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(900)
                ->build()
        );

        $response = $this->queryHandler->handle(new FindBestEfforts());

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
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-02 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );

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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindBestEffortsQueryHandler($this->getConnection());
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityBestEffortRepository = $this->getContainer()->get(ActivityBestEffortRepository::class);
    }
}
