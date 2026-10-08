<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\UpdateSegment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Domain\Segment\UpdateSegment\UpdateSegment;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;

class UpdateSegmentCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Old name'))
            ->withIsFavourite(false)
            ->withType(SegmentType::CUSTOM)
            ->build());
        $this->getContainer()->get(SegmentEffortRepository::class)->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('1'))
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->build());

        $this->commandBus->dispatch(UpdateSegment::fromPayload([
            'segmentId' => 'segment-1',
            'name' => 'New name',
            'isFavourite' => true,
        ]));

        $segment = $this->getContainer()->get(SegmentRepository::class)->find(SegmentId::fromUnprefixed('1'));
        $this->assertEquals(Name::fromString('New name'), $segment->getOriginalName());
        $this->assertTrue($segment->isFavourite());
    }

    public function testHandleThrowsForAnImportedSegment(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Old name'))
            ->withIsFavourite(false)
            ->withType(SegmentType::IMPORTED)
            ->build());
        $this->getContainer()->get(SegmentEffortRepository::class)->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('1'))
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->build());

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Segments imported from Strava cannot be edited.'));

        $this->commandBus->dispatch(UpdateSegment::fromPayload([
            'segmentId' => 'segment-1',
            'name' => 'New name',
            'isFavourite' => true,
        ]));
    }

    public function testHandleThrowsWhenSegmentDoesNotExist(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The segment does not exist.'));

        $this->commandBus->dispatch(UpdateSegment::fromPayload([
            'segmentId' => 'segment-1',
            'name' => 'Old name',
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
