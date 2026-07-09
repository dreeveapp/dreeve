<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

use App\Domain\Activity\Math;

final readonly class GeoMath
{
    private const float EARTH_RADIUS_IN_METERS = 6371000.0;

    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2.0) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2.0) ** 2;

        return self::EARTH_RADIUS_IN_METERS * 2.0 * atan2(sqrt($a), sqrt(1.0 - Math::clamp($a, 0.0, 1.0)));
    }

    public static function semicirclesToDegrees(float $semicircles): float
    {
        return $semicircles * 180 / 2 ** 31;
    }
}
