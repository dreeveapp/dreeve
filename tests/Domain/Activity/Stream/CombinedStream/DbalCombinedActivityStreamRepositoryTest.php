<?php

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamTypes;
use App\Domain\Activity\Stream\CombinedStream\DbalCombinedActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class DbalCombinedActivityStreamRepositoryTest extends ContainerTestCase
{
    private CombinedActivityStreamRepository $combinedActivityStreamRepository;

    public function testAddAndFindOneForActivityAndUnitSystem(): void
    {
        $combinedActivityStream = CombinedActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->build();
        $this->combinedActivityStreamRepository->add($combinedActivityStream);
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withUnitSystem(UnitSystem::IMPERIAL)
                ->build()
        );
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test2'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->build()
        );

        $this->assertEquals(
            $combinedActivityStream,
            $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                activityId: ActivityId::fromUnprefixed('test'),
                unitSystem: UnitSystem::METRIC
            )
        );
    }

    public function testFindOneForActivityAndUnitSystemWhenThereIsNoCombinedStream(): void
    {
        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessageIsOrContains('CombinedActivityStream not found');

        $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
            activityId: ActivityId::fromUnprefixed('test'),
            unitSystem: UnitSystem::METRIC
        );
    }

    public function testCountChartableStreamTypesFor(): void
    {
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->withStreamTypes(CombinedStreamTypes::fromArray([
                    // Only ALTITUDE and WATTS are chartable, the other three are not.
                    CombinedStreamType::TIME,
                    CombinedStreamType::DISTANCE,
                    CombinedStreamType::LAT_LNG,
                    CombinedStreamType::ALTITUDE,
                    CombinedStreamType::WATTS,
                ]))
                ->build()
        );

        $this->assertEquals(
            2,
            $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
                activityId: ActivityId::fromUnprefixed('test'),
                unitSystem: UnitSystem::METRIC
            )
        );
    }

    public function testCountChartableStreamTypesForWhenThereIsNoCombinedStream(): void
    {
        $this->assertEquals(
            0,
            $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
                activityId: ActivityId::fromUnprefixed('test'),
                unitSystem: UnitSystem::METRIC
            )
        );
    }

    public function testCountChartableStreamTypesForWhenTheUnitSystemDoesNotMatch(): void
    {
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->build()
        );

        $this->assertEquals(
            0,
            $this->combinedActivityStreamRepository->countChartableStreamTypesFor(
                activityId: ActivityId::fromUnprefixed('test'),
                unitSystem: UnitSystem::IMPERIAL
            )
        );
    }

    public function testFindActivityIdsThatNeedStreamCombining(): void
    {
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withSportType(SportType::RIDE)
                    ->withActivityId(ActivityId::fromUnprefixed('test'))
                    ->build(),
                []
            )
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([2])
                ->build()
        );

        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-3'))
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
                ->withData([])
            ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([])
                ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-5'))
                    ->build(),
                []
            )
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-5'))
                ->withStreamType(StreamType::TIME)
                ->withData([1])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-5'))
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([2])
                ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-6'))
                    ->build(),
                []
            )
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-6'))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-6'))
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([2])
                ->build()
        );

        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-6'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->build()
        );

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('test-5')]),
            $this->combinedActivityStreamRepository->findActivityIdsThatNeedStreamCombining(
                UnitSystem::METRIC
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->combinedActivityStreamRepository = new DbalCombinedActivityStreamRepository(
            $this->getConnection()
        );
    }
}
