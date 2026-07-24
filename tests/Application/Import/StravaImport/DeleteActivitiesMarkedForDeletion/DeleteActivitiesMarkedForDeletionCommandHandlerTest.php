<?php

namespace App\Tests\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion;

use App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion\DeleteActivitiesMarkedForDeletion;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Repository\Pagination;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\BestEffort\ActivityBestEffortBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class DeleteActivitiesMarkedForDeletionCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $output = new SpyOutput();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1000))
                ->withName('Delete this one')
                ->build(),
            []
        ));

        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(SegmentEffortRepository::class)->add($segmentEffortOne);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withName('Delete this one as well')
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build(),
            []
        ));
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed(1000))
                ->withSegmentEffortId(SegmentEffortId::random())
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build()
        );
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed(1000))
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build()
        );
        $this->getContainer()->get(ActivitySplitRepository::class)->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->withUnitSystem(UnitSystem::IMPERIAL)
            ->withSplitNumber(3)
            ->build());

        $this->getContainer()->get(ActivityLapRepository::class)->add(ActivityLapBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->build());

        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->build());

        $this->getContainer()->get(ActivityRepository::class)->markActivitiesForDeletion(ActivityIds::fromArray([
            ActivityId::fromUnprefixed(1000),
            ActivityId::fromUnprefixed(1001),
        ]));

        $this->commandBus->dispatch(new DeleteActivitiesMarkedForDeletion($output));
        $this->assertMatchesTextSnapshot($output);

        $this->assertEquals(
            1,
            $this->getContainer()->get(ActivityIdRepository::class)->count()
        );
        $this->assertCount(
            0,
            $this->getContainer()->get(SegmentEffortRepository::class)->findByActivityId(ActivityId::fromUnprefixed(1001))
        );
        $this->assertCount(
            0,
            $this->getContainer()->get(SegmentRepository::class)->findAll(Pagination::fromOffsetAndLimit(0, 100))
        );
        $this->assertCount(
            0,
            $this->getContainer()->get(ActivityStreamRepository::class)->findByActivityId(ActivityId::fromUnprefixed(1001))
        );
        $this->assertCount(
            0,
            $this->getContainer()->get(ActivitySplitRepository::class)->findBy(
                ActivityId::fromUnprefixed(1001),
                UnitSystem::IMPERIAL
            )
        );
        $this->assertCount(
            0,
            $this->getContainer()->get(ActivityLapRepository::class)->findBy(
                ActivityId::fromUnprefixed(1001),
            )
        );
        $this->assertEquals(
            0,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityBestEffort WHERE activityId = "activity-1001"')->fetchOne()
        );
    }

    public function testHandleWhenNoActivitiesMarkedForDeletion(): void
    {
        $output = new SpyOutput();

        $this->commandBus->dispatch(new DeleteActivitiesMarkedForDeletion($output));
        $this->assertMatchesTextSnapshot($output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
