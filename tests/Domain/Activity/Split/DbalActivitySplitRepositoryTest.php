<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Split\ActivitySplits;
use App\Domain\Activity\Split\DbalActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

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

    public function testFindActivityIdsThatNeedSplitCalculation(): void
    {
        $this->addActivity('run-file-import', SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'run-file-import',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('walk-file-import', SportType::WALK, ImportSource::TCX_FILE);
        $this->addStreams(
            id: 'walk-file-import',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('swim-file-import', SportType::POOL_SWIM, ImportSource::GPX_FILE);
        $this->addStreams(
            id: 'swim-file-import',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('run-imported-from-strava', SportType::RUN, ImportSource::STRAVA_API);
        $this->addStreams(
            id: 'run-imported-from-strava',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('ride-file-import', SportType::RIDE, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'ride-file-import',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('run-with-splits', SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'run-with-splits',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('run-with-splits'))
            ->withSplitNumber(1)
            ->build());

        $this->addActivity('run-without-time-stream', SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'run-without-time-stream',
            streams: [
                StreamType::DISTANCE->value => [0.0, 100.0, 200.0],
            ],
        );

        $this->addActivity('run-without-distance-stream', SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'run-without-distance-stream',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
            ],
        );

        $this->addActivity('run-with-empty-distance-stream', SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams(
            id: 'run-with-empty-distance-stream',
            streams: [
                StreamType::TIME->value => [0, 1, 2],
                StreamType::DISTANCE->value => [],
            ],
        );

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('run-file-import'),
                ActivityId::fromUnprefixed('swim-file-import'),
                ActivityId::fromUnprefixed('walk-file-import'),
            ]),
            $this->activitySplitRepository->findActivityIdsThatNeedSplitCalculation(),
        );
    }

    private function addActivity(string $id, SportType $sportType, ImportSource $importSource = ImportSource::STRAVA_API): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withSportType($sportType)
                ->withImportSource($importSource)
                ->build(),
            [],
        ));
    }

    /**
     * @param array<string, list<int|float>> $streams
     */
    private function addStreams(string $id, array $streams): void
    {
        foreach ($streams as $streamType => $data) {
            $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }
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
