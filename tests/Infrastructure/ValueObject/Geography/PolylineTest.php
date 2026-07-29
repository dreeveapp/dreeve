<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;
use PHPUnit\Framework\TestCase;

class PolylineTest extends TestCase
{
    public function testFromEncodedPolyline(): void
    {
        $coordinates = [[51.2194, 4.4025], [51.2200, 4.4100]];

        self::assertEqualsWithDelta(
            $coordinates,
            Polyline::fromEncodedPolyline(EncodedPolyline::fromCoordinates($coordinates))->getLatLngCoordinates(),
            0.00001
        );
    }

    public function testDensifyLeavesShortSegmentsAlone(): void
    {
        // Every segment is already well under the default 0.002 degree step.
        $coordinates = [[51.2194, 4.4025], [51.2200, 4.4030], [51.2205, 4.4035]];

        self::assertSame(
            $coordinates,
            Polyline::fromLatLngCoordinates($coordinates)->densify()->getLatLngCoordinates()
        );
    }

    public function testDensifyInterpolatesLongSegments(): void
    {
        $densified = Polyline::fromLatLngCoordinates([[51.0, 4.0], [51.0, 5.0]])
            ->densify()
            ->getLatLngCoordinates();

        self::assertCount(501, $densified);
        self::assertSame([51.0, 4.0], $densified[0]);
        self::assertSame([51.0, 5.0], $densified[500]);
        self::assertEqualsWithDelta([51.0, 4.5], $densified[250], 0.00001);
    }

    public function testDensifyTakesTheShortWayAroundTheAntimeridian(): void
    {
        $densified = Polyline::fromLatLngCoordinates([[-16.98, 179.98], [-17.46, -179.14]])
            ->densify()
            ->getLatLngCoordinates();

        foreach ($densified as [$latitude, $longitude]) {
            self::assertGreaterThan(179.0, abs($longitude), sprintf('Longitude %s is nowhere near the antimeridian', $longitude));
            self::assertLessThanOrEqual(180.0, abs($longitude));
        }
        self::assertGreaterThan(2, count($densified));
    }

    public function testDensifyStaysWithinItsCoordinateBudget(): void
    {
        $densified = Polyline::fromLatLngCoordinates([[0.0, 0.0], [0.0, 90.0]])
            ->densify(maxCoordinates: 1000)
            ->getLatLngCoordinates();

        self::assertLessThanOrEqual(1010, count($densified));
        self::assertGreaterThan(900, count($densified));
    }

    public function testDensifyReturnsOriginalPolylineWhenLessThanTwoPoints(): void
    {
        self::assertSame(
            [[51.2194, 4.4025]],
            Polyline::fromLatLngCoordinates([[51.2194, 4.4025]])->densify()->getLatLngCoordinates()
        );
    }

    public function testSanitize(): void
    {
        self::assertSame(
            [[51.2194, 4.4025], [50.0, -179.0]],
            Polyline::fromLatLngCoordinates([
                [51.2194, 4.4025],
                [NAN, 4.0],
                [51.0, INF],
                [91.0, 4.0],
                [-90.5, 4.0],
                [50.0, 181.0],
            ])->sanitize()->getLatLngCoordinates()
        );
    }

    public function testSimplifyReturnsOriginalPolylineWhenLessThanTwoPoints(): void
    {
        $coordinates = [
            [51.2194, 4.4025],
        ];
        $polyline = Polyline::fromLatLngCoordinates($coordinates);

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates($coordinates),
            (string) $polyline->simplify()->encode(),
        );
    }

    public function testSimplifyRemovesPointsOnStraightLine(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [0.5, 0.5],
            [1.0, 1.02],
            [1.5, 1.5],
            [2.2, 2.2],
            [2.95, 3.0],
            [3.5, 3.5],
            [4.0, 4.0],
        ];

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates([
                [0.0, 0.0],
                [4.0, 4.0],
            ]),
            (string) Polyline::fromLatLngCoordinates($coordinates)->simplify(0.1)->encode(),
        );
    }

    public function testSimplifyKeepsCorner(): void
    {
        $coordinates = [
            [0.0, 0.0],
            [0.5, 0.0],
            [1.0, 0.02],
            [1.0, 0.0],
            [1.0, 0.5],
            [1.02, 0.7],
            [1.0, 1.0],
            [1.5, 1.0],
            [2.0, 1.0],
        ];

        self::assertSame(
            (string) EncodedPolyline::fromCoordinates([
                [0.0, 0.0],
                [1.0, 0.0],
                [1.0, 1.0],
                [2.0, 1.0],
            ]),
            (string) Polyline::fromLatLngCoordinates($coordinates)->simplify(0.1)->encode(),
        );
    }

    public function testSimplifyWithDefaultToleranceKeepsGpsScaleRoute(): void
    {
        $centerLat = 51.2194;
        $centerLng = 4.4025;
        $radius = 0.03;
        $numberOfPoints = 200;

        $coordinates = [];
        for ($i = 0; $i < $numberOfPoints; ++$i) {
            $angle = 2 * M_PI * $i / $numberOfPoints;
            $coordinates[] = [
                $centerLat + $radius * sin($angle),
                $centerLng + $radius * cos($angle),
            ];
        }

        $simplified = Polyline::fromLatLngCoordinates($coordinates)->simplify();

        $decodedPoints = EncodedPolyline::fromString((string) $simplified->encode())->decodeAndPairLatLng();

        self::assertGreaterThan(40, count($decodedPoints));
        self::assertLessThan(150, count($decodedPoints));
    }

    public function testEncodeReturnsEncodedPolyline(): void
    {
        $coordinates = [
            [51.2194, 4.4025],
            [51.2200, 4.4030],
        ];

        self::assertEquals(
            EncodedPolyline::fromCoordinates($coordinates),
            Polyline::fromLatLngCoordinates($coordinates)->encode(),
        );
    }
}
