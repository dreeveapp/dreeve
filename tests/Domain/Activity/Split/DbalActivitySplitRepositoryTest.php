<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Split\ActivitySplits;
use App\Domain\Activity\Split\DbalActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalActivitySplitRepositoryTest extends ContainerTestCase
{
    private ActivitySplitRepository $activitySplitRepository;
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;

    public function testAddAndFindBy(): void
    {
        $activitySplitTwo = ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(2)
            ->build();
        $this->activitySplitRepository->add($activitySplitTwo);

        $activitySplitOne = ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(1)
            ->build();
        $this->activitySplitRepository->add($activitySplitOne);

        $activitySplitThree = ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(3)
            ->build();
        $this->activitySplitRepository->add($activitySplitThree);

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(3)
            ->build());

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::IMPERIAL)
            ->withSplitNumber(3)
            ->build());

        $this->assertEquals(
            ActivitySplits::fromArray([$activitySplitOne, $activitySplitTwo, $activitySplitThree]),
            $this->activitySplitRepository->findBy(
                activityId: ActivityId::fromUnprefixed('test'),
                unitSystem: UnitSystem::METRIC
            )
        );
    }

    public function testIsImportedForActivity(): void
    {
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::IMPERIAL)
            ->withSplitNumber(3)
            ->build()
        );

        $this->assertTrue($this->activitySplitRepository->isImportedForActivity(ActivityId::fromUnprefixed('test')));
        $this->assertFalse($this->activitySplitRepository->isImportedForActivity(ActivityId::fromUnprefixed('test2')));
    }

    public function testDeleteForActivity(): void
    {
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(1)
            ->build());

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(2)
            ->build());

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(3)
            ->build());

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(3)
            ->build());

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withUnitSystem(UnitSystem::IMPERIAL)
            ->withSplitNumber(3)
            ->build());

        $this->activitySplitRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivitySplit')->fetchOne()
        );
    }

    public function testFindActivityIdsWithoutGap(): void
    {
        $this->addActivity('run-without-gap', SportType::RUN);
        $this->addActivity('run-with-gap', SportType::RUN);
        $this->addActivity('trail-run-without-gap', SportType::TRAIL_RUN);
        $this->addActivity('ride-without-gap', SportType::RIDE);

        // Run activity without GAP.
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('run-without-gap'))
            ->withSplitNumber(1)
            ->build());

        // Run activity with GAP already calculated.
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('run-with-gap'))
            ->withSplitNumber(1)
            ->withGapPace(SecPerKm::from(300))
            ->build());

        // Trail run without GAP.
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('trail-run-without-gap'))
            ->withSplitNumber(1)
            ->build());

        // Ride without GAP — should NOT be returned.
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('ride-without-gap'))
            ->withSplitNumber(1)
            ->build());

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('run-without-gap'),
                ActivityId::fromUnprefixed('trail-run-without-gap'),
            ]),
            $this->activitySplitRepository->findActivityIdsWithoutGap(),
        );
    }

    public function testFindActivityIdsWithoutGapWhenAllHaveGap(): void
    {
        $this->addActivity('run-with-gap', SportType::RUN);

        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('run-with-gap'))
            ->withSplitNumber(1)
            ->withGapPace(SecPerKm::from(300))
            ->build());

        $this->assertEquals(
            ActivityIds::fromArray([]),
            $this->activitySplitRepository->findActivityIdsWithoutGap(),
        );
    }

    public function testUpdate(): void
    {
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withSplitNumber(1)
            ->build());

        $splits = $this->activitySplitRepository->findBy(ActivityId::fromUnprefixed('test'), UnitSystem::METRIC);
        $this->assertNull($splits->toArray()[0]->getGapPaceInSecondsPerKm());

        $split = $splits->toArray()[0]
            ->withGapPace(SecPerKm::from(350.5));
        $this->activitySplitRepository->update($split);

        $updatedSplits = $this->activitySplitRepository->findBy(ActivityId::fromUnprefixed('test'), UnitSystem::METRIC);
        $this->assertEqualsWithDelta(350.5, $updatedSplits->toArray()[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    private function addActivity(string $id, SportType $sportType): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withSportType($sportType)
                ->build(),
            [],
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activitySplitRepository = new DbalActivitySplitRepository($this->getConnection());
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
    }
}
