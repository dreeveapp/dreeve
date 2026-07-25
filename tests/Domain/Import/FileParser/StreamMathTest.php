<?php

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\FileParser\StreamMath;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class StreamMathTest extends TestCase
{
    #[TestWith(data: [[], 0.0])]
    #[TestWith(data: [[null, null], 0.0])]
    #[TestWith(data: [[10.0, 12.0, 11.0, 14.0], 5.0])]
    #[TestWith(data: [[10.0, null, 12.0, null, null, 11.0, 14.0], 5.0])]
    #[TestWith(data: [[14.0, 12.0, 10.0], 0.0])]
    public function testElevationGain(array $altitudes, float $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            StreamMath::elevationGain($altitudes)
        );
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
