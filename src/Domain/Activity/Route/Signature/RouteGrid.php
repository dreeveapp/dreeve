<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;

final readonly class RouteGrid
{
    public const int GRID_SIZE = 100;
    public const float DENSIFY_STEP_IN_DEGREES = 0.0005;
    public const float THRESHOLD = 0.75;
    public const int MIN_CELL_COUNT = 10;
    public const float MAX_DISTANCE_RATIO = 0.9;
    public const int WAYPOINT_COUNT = 7;
    public const float MAX_WAYPOINT_DRIFT_RATIO = 0.10;
    public const float MAX_WAYPOINT_DRIFT_IN_METER = 750;

    private const int LAT_OFFSET = 9_000_000;
    private const int LNG_OFFSET = 18_000_000;
    private const int LNG_CELL_COUNT = 36_000_000 / self::GRID_SIZE + 1;

    public function cellsFor(EncodedPolyline $polyline): RouteCells
    {
        $latLngCoordinates = $this->densifiedCoordinates($polyline);

        $cells = [];
        foreach ($latLngCoordinates as [$latitude, $longitude]) {
            $latitudeE5 = (int) round($latitude * 100_000);
            $longitudeE5 = (int) round($longitude * 100_000);

            $cells[intdiv($latitudeE5 + self::LAT_OFFSET, self::GRID_SIZE) * self::LNG_CELL_COUNT
                + intdiv($longitudeE5 + self::LNG_OFFSET, self::GRID_SIZE)] = true;
        }

        return RouteCells::fromFlippedCellKeys($cells);
    }

    public function waypointsFor(EncodedPolyline $polyline): RouteWaypoints
    {
        $latLngCoordinates = $this->densifiedCoordinates($polyline);

        $coordinateCount = count($latLngCoordinates);
        if ($coordinateCount < 2) {
            return RouteWaypoints::empty();
        }

        $cumulativeLength = [0.0];
        for ($i = 1; $i < $coordinateCount; ++$i) {
            $cumulativeLength[$i] = $cumulativeLength[$i - 1] + hypot(
                $latLngCoordinates[$i][0] - $latLngCoordinates[$i - 1][0],
                $latLngCoordinates[$i][1] - $latLngCoordinates[$i - 1][1],
            );
        }

        if (($totalLength = $cumulativeLength[$coordinateCount - 1]) <= 0) {
            return RouteWaypoints::empty();
        }

        $waypoints = [];
        $index = 0;
        for ($n = 1; $n <= self::WAYPOINT_COUNT; ++$n) {
            $target = $totalLength * $n / (self::WAYPOINT_COUNT + 1);
            while ($index < $coordinateCount - 1 && $cumulativeLength[$index] < $target) {
                ++$index;
            }
            $waypoints[] = (int) round($latLngCoordinates[$index][0] * 100_000);
            $waypoints[] = (int) round($latLngCoordinates[$index][1] * 100_000);
        }

        return RouteWaypoints::fromArray($waypoints);
    }

    public function checksumFor(EncodedPolyline $polyline): string
    {
        return hash('crc32b', self::GRID_SIZE.':'.self::DENSIFY_STEP_IN_DEGREES.':'.self::WAYPOINT_COUNT.':'.$polyline);
    }

    /**
     * @return list<array{float, float}>
     */
    private function densifiedCoordinates(EncodedPolyline $polyline): array
    {
        return Polyline::fromEncodedPolyline($polyline)
            ->sanitize()
            ->densify(maxStepInDegrees: self::DENSIFY_STEP_IN_DEGREES)
            ->getLatLngCoordinates();
    }
}
