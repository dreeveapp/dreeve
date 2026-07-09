<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\GeoMath;
use PHPUnit\Framework\TestCase;

class GeoMathTest extends TestCase
{
    public function testHaversineDistanceBetweenSamePointIsZero(): void
    {
        self::assertSame(
            0.0,
            GeoMath::haversineDistance(
                lat1: 50.8503,
                lon1: 4.3517,
                lat2: 50.8503,
                lon2: 4.3517,
            )
        );
    }

    public function testHaversineDistanceCalculatesDistanceBetweenBrusselsAndLondon(): void
    {
        $distance = GeoMath::haversineDistance(
            lat1: 50.8503,
            lon1: 4.3517,
            lat2: 51.5072,
            lon2: -0.1276,
        );

        // Actual distance is approximately 320km
        self::assertEqualsWithDelta(
            320_000,
            $distance,
            2_000
        );
    }

    public function testHaversineDistanceIsSymmetric(): void
    {
        $forward = GeoMath::haversineDistance(
            lat1: 50.8503,
            lon1: 4.3517,
            lat2: 51.5072,
            lon2: -0.1276,
        );

        $reverse = GeoMath::haversineDistance(
            lat1: 51.5072,
            lon1: -0.1276,
            lat2: 50.8503,
            lon2: 4.3517,
        );

        self::assertEqualsWithDelta(
            $forward,
            $reverse,
            0.001
        );
    }

    public function testSemicirclesToDegreesConvertsHalfCircle(): void
    {
        self::assertEqualsWithDelta(
            180.0,
            GeoMath::semicirclesToDegrees(2 ** 31),
            0.000001
        );
    }

    public function testSemicirclesToDegreesConvertsQuarterCircle(): void
    {
        self::assertEqualsWithDelta(
            90.0,
            GeoMath::semicirclesToDegrees(2 ** 30),
            0.000001
        );
    }

    public function testSemicirclesToDegreesConvertsZero(): void
    {
        self::assertSame(
            0.0,
            GeoMath::semicirclesToDegrees(0)
        );
    }
}
