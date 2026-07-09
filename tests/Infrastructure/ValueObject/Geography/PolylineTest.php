<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;
use PHPUnit\Framework\TestCase;

class PolylineTest extends TestCase
{
    public function testSimplifyReturnsOriginalPolylineWhenLessThanTwoPoints(): void
    {
        $coordinates = [
            [51.2194, 4.4025],
        ];
        $polyline = Polyline::fromCoordinates($coordinates);

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates($coordinates),
            (string) $polyline->simplify()->encode(),
        );
    }

    public function testSimplifyRemovesPointsOnStraightLine(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 1.0],
            [2.0, 2.0],
            [3.0, 3.0],
        ];

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates([
                [0.0, 0.0],
                [3.0, 3.0],
            ]),
            (string) Polyline::fromCoordinates($coordinates)->simplify(0.1)->encode(),
        );
    }

    public function testSimplifyKeepsCorner(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0],
            [1.0, 1.0],
            [2.0, 1.0],
        ];

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates($coordinates),
            (string) Polyline::fromCoordinates($coordinates)->simplify(0.1)->encode(),
        );
    }

    public function testSimplifyWithLargeToleranceKeepsOnlyEndpoints(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [1.0, 0.0],
            [1.0, 1.0],
            [2.0, 1.0],
        ];

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates([
                [0.0, 0.0],
                [2.0, 1.0],
            ]),
            (string) Polyline::fromCoordinates($coordinates)->simplify(10)->encode(),
        );
    }

    public function testEncodeReturnsEncodedPolyline(): void
    {
        $coordinates = [
            [51.2194, 4.4025],
            [51.2200, 4.4030],
        ];

        self::assertEquals(
            EncodedPolyline::fromCoordinates($coordinates),
            Polyline::fromCoordinates($coordinates)->encode(),
        );
    }
}
