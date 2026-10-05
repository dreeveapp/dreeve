<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateMovingStream;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\SpyOutput;
use PHPUnit\Framework\Attributes\DataProvider;

class CalculateMovingStreamTest extends ContainerTestCase
{
    private CalculateMovingStream $calculateMovingStream;
    private ActivityStreamRepository $activityStreamRepository;

    /**
     * @param array<string, list<mixed>> $streams
     * @param list<bool>|null            $expectedMovingStream
     */
    #[DataProvider('provideStreams')]
    public function testProcess(array $streams, ?array $expectedMovingStream): void
    {
        foreach ($streams as $streamType => $data) {
            $this->activityStreamRepository->add(
                ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed(1))
                    ->withStreamType(StreamType::from($streamType))
                    ->withData($data)
                    ->build()
            );
        }

        $this->calculateMovingStream->process(new SpyOutput());

        $this->assertSame($expectedMovingStream, $this->activityStreamRepository
            ->findByActivityId(ActivityId::fromUnprefixed(1))
            ->filterOnType(StreamType::MOVING)?->getData());
    }

    public static function provideStreams(): iterable
    {
        yield 'velocity below 0.5 m/s means stopped' => [
            [StreamType::TIME->value => [0, 1, 2, 3], StreamType::VELOCITY->value => [0.0, 1.0, 0.2, 2.0]],
            [false, true, false, true],
        ];
        yield 'distance deltas when velocity is missing, first point counts as moving' => [
            [StreamType::TIME->value => [0, 10, 20], StreamType::DISTANCE->value => [0.0, 2.0, 20.0]],
            [true, false, true],
        ];
        yield 'coordinates when velocity and distance are missing' => [
            [StreamType::TIME->value => [0, 100], StreamType::LAT_LNG->value => [[0.0, 0.0], [0.0, 0.001]]],
            [true, true],
        ];
        yield 'an existing moving stream is kept' => [
            [StreamType::TIME->value => [0], StreamType::VELOCITY->value => [0.0], StreamType::MOVING->value => [true]],
            [true],
        ];
        yield 'a velocity stream of only zeroes' => [
            [StreamType::TIME->value => [0, 5, 10], StreamType::VELOCITY->value => [0.0, 0.0, 0.0]],
            null,
        ];
        yield 'a distance stream of only zeroes' => [
            [StreamType::TIME->value => [0, 5, 10], StreamType::DISTANCE->value => [0.0, 0.0, 0.0]],
            null,
        ];
        yield 'nothing moved' => [
            [StreamType::TIME->value => [0, 1, 2, 3], StreamType::DISTANCE->value => [100.0, 100.0, 100.0, 100.0]],
            null,
        ];
        yield 'no speed source' => [
            [StreamType::TIME->value => [0, 1], StreamType::HEART_RATE->value => [100, 110]],
            null,
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateMovingStream = $this->getContainer()->get(CalculateMovingStream::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
    }
}
