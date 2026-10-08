<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\DeleteSegment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Import\ImportMode;
use App\Domain\Segment\DeleteSegment\DeleteSegment;
use App\Domain\Segment\DeleteSegment\DeleteSegmentCommandHandler;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;

class DeleteSegmentCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
        $activityScanRepository = $this->getContainer()->get(ActivityScanRepository::class);
        foreach (['1', '2'] as $id) {
            $segmentRepository->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withType(SegmentType::CUSTOM)
                ->build());
            $segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($id))
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->build());
            $activityScanRepository->add(ActivityScan::create(ActivityId::fromUnprefixed('1'), ActivityScanType::CUSTOM_SEGMENT, 'segment-'.$id));
        }

        $this->commandBus->dispatch(DeleteSegment::fromPayload(['segmentId' => 'segment-1']));

        $this->assertEquals(
            ['segment-2'],
            $this->getConnection()->executeQuery('SELECT segmentId FROM Segment')->fetchFirstColumn()
        );
        $this->assertEquals(
            ['segmentEffort-2'],
            $this->getConnection()->executeQuery('SELECT segmentEffortId FROM SegmentEffort')->fetchFirstColumn()
        );
        $this->assertEquals(
            ['segment-2'],
            $this->getConnection()->executeQuery('SELECT subjectId FROM ActivityScan')->fetchFirstColumn()
        );
    }

    public function testItRefusesToDeleteAnImportedSegmentInStravaImportMode(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withType(SegmentType::IMPORTED)
            ->build());

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Segments imported from Strava can only be deleted when running in file import mode.'));

        $this->commandBus->dispatch(DeleteSegment::fromPayload(['segmentId' => 'segment-1']));
    }

    public function testItDeletesAnImportedSegmentInFileImportMode(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withType(SegmentType::IMPORTED)
            ->build());

        new DeleteSegmentCommandHandler(
            segmentRepository: $this->getContainer()->get(SegmentRepository::class),
            segmentEffortRepository: $this->getContainer()->get(SegmentEffortRepository::class),
            activityScanRepository: $this->getContainer()->get(ActivityScanRepository::class),
            importMode: ImportMode::FILES,
        )->handle(DeleteSegment::fromPayload(['segmentId' => 'segment-1']));

        $this->assertEquals(0, $this->getConnection()->executeQuery('SELECT COUNT(*) FROM Segment')->fetchOne());
    }

    public function testItThrowsWhenTheSegmentDoesNotExist(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The segment does not exist.'));

        $this->commandBus->dispatch(DeleteSegment::fromPayload(['segmentId' => 'segment-1']));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
