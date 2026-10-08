<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\BestEffort\ActivityBestEfforts;
use App\Domain\Activity\BestEffort\DbalActivityBestEffortRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class DbalActivityBestEffortRepositoryTest extends ContainerTestCase
{
    private ActivityBestEffortRepository $activityBestEffortRepository;

    public function testAdd(): void
    {
        $this->assertFalse($this->activityBestEffortRepository->hasData());
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->assertTrue($this->activityBestEffortRepository->hasData());
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(1000))
                ->withTimeInSeconds(3600)
                ->build()
        );

        $this->assertEquals(
            [
                [
                    'activityId' => 'activity-test',
                    'distanceInMeter' => 10000,
                    'sportType' => 'Ride',
                    'timeInSeconds' => 3600,
                ],
                [
                    'activityId' => 'activity-test-2',
                    'distanceInMeter' => 10000,
                    'sportType' => 'Ride',
                    'timeInSeconds' => 3600,
                ],
                [
                    'activityId' => 'activity-test-2',
                    'distanceInMeter' => 1000,
                    'sportType' => 'Ride',
                    'timeInSeconds' => 3600,
                ],
            ],
            $this->getConnection()->executeQuery('SELECT * FROM ActivityBestEffort')->fetchAllAssociative(),
        );
    }

    public function testFindActivityIdsThatNeedBestEffortsCalculation(): void
    {
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                    ->build(),
                []
            )
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                    ->build(),
                []
            )
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1, 10000])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                ->withStreamType(StreamType::TIME)
                ->withData([1, 2, 3, 4, 5])
                ->build()
        );

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('test-4')]),
            $this->activityBestEffortRepository->findActivityIdsThatNeedBestEffortsCalculation()
        );
    }

    public function testFindByActivity(): void
    {
        // Same distance for two activities, the faster one holds the record.
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('fastest'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('slowest'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        // A distance only the slowest activity has ridden, so it holds that record.
        $slowestOnlyBestEffort = ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('slowest'))
            ->withSportType(SportType::RIDE)
            ->withDistanceInMeter(Meter::from(20000))
            ->withTimeInSeconds(7200)
            ->build();
        $this->activityBestEffortRepository->add($slowestOnlyBestEffort);

        $this->assertEquals(
            ActivityBestEfforts::fromArray([$slowestOnlyBestEffort]),
            $this->activityBestEffortRepository->findByActivity(ActivityId::fromUnprefixed('slowest'))
        );
    }

    public function testFindPersonalRecords(): void
    {
        $fastestRide = ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('fastest'))
            ->withSportType(SportType::RIDE)
            ->withDistanceInMeter(Meter::from(10000))
            ->withTimeInSeconds(1800)
            ->build();
        $this->activityBestEffortRepository->add($fastestRide);
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('slowest'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $longestRide = ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('slowest'))
            ->withSportType(SportType::RIDE)
            ->withDistanceInMeter(Meter::from(20000))
            ->withTimeInSeconds(7200)
            ->build();
        $this->activityBestEffortRepository->add($longestRide);
        // Records are tracked per sport type.
        $run = ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('run'))
            ->withSportType(SportType::RUN)
            ->withDistanceInMeter(Meter::from(10000))
            ->withTimeInSeconds(2400)
            ->build();
        $this->activityBestEffortRepository->add($run);

        $this->assertEquals(
            ActivityBestEfforts::fromArray([$fastestRide, $longestRide, $run]),
            $this->activityBestEffortRepository->findPersonalRecords()
        );
    }

    public function testFindMostRecentStartDateTimeOfActivitiesWithBestEfforts(): void
    {
        $this->assertNull($this->activityBestEffortRepository->findMostRecentStartDateTimeOfActivitiesWithBestEfforts());

        foreach (['2023-01-01', '2024-06-15', '2025-03-01'] as $startDateTime) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($startDateTime))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
        }
        foreach (['2023-01-01', '2024-06-15'] as $activityWithBestEfforts) {
            $this->activityBestEffortRepository->add(
                ActivityBestEffortBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityWithBestEfforts))
                    ->build()
            );
        }

        $this->assertEquals(
            SerializableDateTime::fromString('2024-06-15'),
            $this->activityBestEffortRepository->findMostRecentStartDateTimeOfActivitiesWithBestEfforts()
        );
    }

    public function testFindByActivityWhenItHoldsNoRecords(): void
    {
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );

        $this->assertEmpty(
            $this->activityBestEffortRepository->findByActivity(ActivityId::fromUnprefixed('unknown'))->toArray()
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(1000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->build());

        $this->activityBestEffortRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityBestEffort')->fetchOne()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->activityBestEffortRepository = new DbalActivityBestEffortRepository(
            $this->getConnection(),
        );
    }
}
