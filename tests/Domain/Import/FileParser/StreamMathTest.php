<?php

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\FileParser\StreamMath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class StreamMathTest extends TestCase
{
    #[DataProvider('provideElevationGain')]
    public function testElevationGain(array $altitudes, float $expectedResult): void
    {
        $this->assertEqualsWithDelta(
            $expectedResult,
            StreamMath::elevationGain($altitudes),
            0.001
        );
    }

    public static function provideElevationGain(): iterable
    {
        yield 'empty' => [[], 0.0];
        yield 'only nulls' => [[null, null], 0.0];
        yield 'climb below threshold' => [[10.0, 11.0, 10.5, 11.5], 0.0];
        yield 'descent' => [[14.0, 12.0, 10.0, 5.0, 1.0], 0.0];
        yield 'clean climb' => [array_map(floatval(...), range(0, 99)), 98.0];
        yield 'clean climb with nulls' => [[0.0, null, 3.0, 6.0, null, null, 9.0], 9.0];
        yield 'flat jitter' => [array_map(static fn (int $i): float => 100.0 + ($i % 2), range(0, 59)), 0.0];
        yield 'climb with jitter' => [array_map(static fn (int $i): float => $i + ($i % 2 ? 1.0 : -1.0), range(0, 99)), 101.0];
        yield 'climb, descent, climb' => [
            [...array_map(floatval(...), range(0, 50)), ...array_map(floatval(...), range(49, 20, -1)), ...array_map(floatval(...), range(21, 80))],
            99.2,
        ];
        yield 'rolling within a few meters' => [
            [...array_map(floatval(...), range(0, 10)), ...array_map(floatval(...), range(9, 0, -1)), ...array_map(floatval(...), range(1, 10))],
            12.0,
        ];
    }

    #[TestWith(data: [[], 0])]
    #[TestWith(data: [[null, null], 0])]
    #[TestWith(data: [[0, 1, 2, 3], 3])]
    #[TestWith(data: [[0, null, 2, null, 3], 3])]
    #[TestWith(data: [[0, 10, 200, 210], 20])]
    #[TestWith(data: [[10, 5, 6], 1])]
    public function testActiveSeconds(array $timestamps, int $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            StreamMath::activeSeconds($timestamps)
        );
    }

    #[TestWith(data: [[], [], []])]
    #[TestWith(data: [[0.0, 5.0, 15.0], [0, 1, 2], [null, 5.0, 10.0]])]
    #[TestWith(data: [[0.0, null, 15.0], [0, 1, 2], [null, null, 7.5]])]
    #[TestWith(data: [[0.0, 5.0], [0, null], [null, null]])]
    #[TestWith(data: [[0.0, 5.0], [5, 5], [null, null]])]
    public function testDeriveVelocityStream(array $distances, array $times, array $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            StreamMath::deriveVelocityStream($distances, $times)
        );
    }

    public function testDeriveDistanceStream(): void
    {
        $this->assertSame(
            [0.0, 0.0, 111.19, 111.19, 222.39],
            StreamMath::deriveDistanceStream([
                null,
                [43.0, 3.0],
                [43.001, 3.0],
                null,
                [43.002, 3.0],
            ])
        );
    }

    public function testDeriveDistanceStreamWithoutCoordinates(): void
    {
        $this->assertSame(
            [0.0, 0.0],
            StreamMath::deriveDistanceStream([null, null])
        );
    }

    public function testEncodePolyline(): void
    {
        $this->assertNull(StreamMath::encodePolyline([]));
        $this->assertNull(StreamMath::encodePolyline([StreamType::LAT_LNG->value => [null, null]]));
        $this->assertNotNull(StreamMath::encodePolyline([StreamType::LAT_LNG->value => [null, [43.0, 3.0], [43.001, 3.001]]]));
    }

    public function testFirstCoordinate(): void
    {
        $this->assertNull(StreamMath::firstCoordinate([]));
        $this->assertNull(StreamMath::firstCoordinate([StreamType::LAT_LNG->value => [null, null]]));

        $coordinate = StreamMath::firstCoordinate([StreamType::LAT_LNG->value => [null, [43.0, 3.0], [44.0, 4.0]]]);
        $this->assertSame(43.0, $coordinate?->getLatitude()->toFloat());
        $this->assertSame(3.0, $coordinate?->getLongitude()->toFloat());
    }
}
