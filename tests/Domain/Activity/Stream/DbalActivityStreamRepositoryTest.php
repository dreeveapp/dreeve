<?php

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;

class DbalActivityStreamRepositoryTest extends ContainerTestCase
{
    private ActivityStreamRepository $activityStreamRepository;

    public function testHasOneAndFindByStreamType(): void
    {
        $stream = ActivityStreamBuilder::fromDefaults()->build();
        $this->activityStreamRepository->add($stream);

        $this->assertTrue($this->activityStreamRepository->hasOneForActivityAndStreamType(
            activityId: $stream->getActivityId(),
            streamType: $stream->getStreamType()
        ));
        $this->assertFalse($this->activityStreamRepository->hasOneForActivityAndStreamType(
            activityId: ActivityId::fromUnprefixed(1),
            streamType: $stream->getStreamType()
        ));
        $this->assertFalse($this->activityStreamRepository->hasOneForActivityAndStreamType(
            activityId: $stream->getActivityId(),
            streamType: StreamType::CADENCE
        ));

        $this->assertEquals(
            ActivityStreams::fromArray([$stream]),
            $this->activityStreamRepository->findByStreamType($stream->getStreamType())
        );
    }

    public function testFindActivityIdsByStreamType(): void
    {
        $stream = ActivityStreamBuilder::fromDefaults()
            ->withStreamType(StreamType::WATTS)
            ->build();
        $this->activityStreamRepository->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withStreamType(StreamType::TIME)
            ->build();
        $this->activityStreamRepository->add($stream);

        $this->assertEquals(
            ActivityIds::fromArray([$stream->getActivityId()]),
            $this->activityStreamRepository->findActivityIdsByStreamType($stream->getStreamType())
        );
    }

    public function testFindOneByActivityAndStreamType(): void
    {
        $streamOne = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStreamType(StreamType::WATTS)
            ->build();
        $this->activityStreamRepository->add($streamOne);
        $streamTwo = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStreamType(StreamType::CADENCE)
            ->build();
        $this->activityStreamRepository->add($streamTwo);
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withStreamType(StreamType::HEART_RATE)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withStreamType(StreamType::CADENCE)
                ->build()
        );

        $this->assertEquals(
            $streamTwo,
            $this->activityStreamRepository->findOneByActivityAndStreamType(
                activityId: ActivityId::fromUnprefixed(1),
                streamType: StreamType::CADENCE
            )
        );
    }

    public function testFindOneByActivityAndStreamTypeItShouldThrow(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityStream activity-1-cadence not found'));

        $this->activityStreamRepository->findOneByActivityAndStreamType(
            activityId: ActivityId::fromUnprefixed(1),
            streamType: StreamType::CADENCE
        );
    }

    public function testFindByActivity(): void
    {
        $streamOne = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStreamType(StreamType::WATTS)
            ->build();
        $this->activityStreamRepository->add($streamOne);
        $streamTwo = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStreamType(StreamType::CADENCE)
            ->build();
        $this->activityStreamRepository->add($streamTwo);
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withStreamType(StreamType::CADENCE)
                ->build()
        );

        $this->assertEquals(
            ActivityStreams::fromArray([$streamOne, $streamTwo]),
            $this->activityStreamRepository->findByActivityId(
                activityId: ActivityId::fromUnprefixed(1),
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamRepository = new DbalActivityStreamRepository(
            $this->getConnection(),
        );
    }
}
