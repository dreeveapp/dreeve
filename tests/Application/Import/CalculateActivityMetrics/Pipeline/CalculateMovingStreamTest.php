<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateMovingStream;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\SpyOutput;

class CalculateMovingStreamTest extends ContainerTestCase
{
    private CalculateMovingStream $calculateMovingStream;
    private ActivityStreamRepository $activityStreamRepository;

    public function testItDerivesMovingFromVelocity(): void
    {
        $this->addStream(1, StreamType::TIME, [0, 1, 2, 3]);
        $this->addStream(1, StreamType::VELOCITY, [0.0, 1.0, 0.2, 2.0]);

        $this->calculateMovingStream->process(new SpyOutput());

        // Threshold is 0.5 m/s.
        $this->assertSame([false, true, false, true], $this->activityStreamRepository
            ->findByActivityId(ActivityId::fromUnprefixed(1))
            ->filterOnType(StreamType::MOVING)?->getData());
    }

    public function testItDerivesMovingFromDistanceWhenVelocityMissing(): void
    {
        $this->addStream(2, StreamType::TIME, [0, 10, 20]);
        $this->addStream(2, StreamType::DISTANCE, [0.0, 2.0, 20.0]);

        $this->calculateMovingStream->process(new SpyOutput());

        // Deltas: 2m/10s = 0.2 m/s (stopped), 18m/10s = 1.8 m/s (moving).
        // First point has no predecessor, so it counts as moving.
        $this->assertSame([true, false, true], $this->activityStreamRepository
            ->findByActivityId(ActivityId::fromUnprefixed(2))
            ->filterOnType(StreamType::MOVING)?->getData());
    }

    public function testItDerivesMovingFromCoordinatesWhenVelocityAndDistanceMissing(): void
    {
        $this->addStream(3, StreamType::TIME, [0, 100]);
        $this->addStream(3, StreamType::LAT_LNG, [[0.0, 0.0], [0.0, 0.001]]);

        $this->calculateMovingStream->process(new SpyOutput());

        // ~111m over 100s ≈ 1.1 m/s.
        $this->assertSame([true, true], $this->activityStreamRepository
            ->findByActivityId(ActivityId::fromUnprefixed(3))
            ->filterOnType(StreamType::MOVING)?->getData());
    }

    public function testItDoesNotOverwriteAnExistingMovingStream(): void
    {
        $this->addStream(4, StreamType::TIME, [0]);
        $this->addStream(4, StreamType::VELOCITY, [0.0]);
        $this->addStream(4, StreamType::MOVING, [true]);

        $this->calculateMovingStream->process(new SpyOutput());

        // Velocity 0.0 would yield [false]; the existing stream must be kept.
        $this->assertSame([true], $this->activityStreamRepository
            ->findByActivityId(ActivityId::fromUnprefixed(4))
            ->filterOnType(StreamType::MOVING)?->getData());
    }

    public function testItSkipsActivitiesWithoutASpeedSource(): void
    {
        $this->addStream(5, StreamType::TIME, [0, 1]);
        $this->addStream(5, StreamType::HEART_RATE, [100, 110]);

        $this->calculateMovingStream->process(new SpyOutput());

        $this->assertFalse($this->activityStreamRepository->hasOneForActivityAndStreamType(
            ActivityId::fromUnprefixed(5),
            StreamType::MOVING,
        ));
    }

    /**
     * @param list<mixed> $data
     */
    private function addStream(int $activityId, StreamType $streamType, array $data): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStreamType($streamType)
                ->withData($data)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateMovingStream = $this->getContainer()->get(CalculateMovingStream::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
    }
}
