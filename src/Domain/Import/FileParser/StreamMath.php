<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Geography\Polyline;

final readonly class StreamMath
{
    // Interval longer than this is treated as a recording gap rather than active time.
    public const int MAX_RECORDING_GAP_IN_SECONDS = 60;

    /**
     * @param list<?float> $altitudes
     */
    public static function elevationGain(array $altitudes): float
    {
        $gain = 0.0;
        $previous = null;
        foreach ($altitudes as $altitude) {
            if (null === $altitude) {
                continue;
            }
            if (null !== $previous && $altitude > $previous) {
                $gain += $altitude - $previous;
            }
            $previous = $altitude;
        }

        return $gain;
    }

    /**
     * @param list<?int> $timestamps
     */
    public static function activeSeconds(array $timestamps): int
    {
        $active = 0;
        $previous = null;
        foreach ($timestamps as $time) {
            if (null === $time) {
                continue;
            }
            if (null !== $previous) {
                $delta = $time - $previous;
                if ($delta > 0 && $delta <= self::MAX_RECORDING_GAP_IN_SECONDS) {
                    $active += $delta;
                }
            }
            $previous = $time;
        }

        return $active;
    }

    /**
     * @param array<string, list<mixed>> $streamMap
     */
    public static function encodePolyline(array $streamMap): ?string
    {
        /** @var list<array{float, float}> $coordinates */
        $coordinates = array_values(array_filter(
            $streamMap[StreamType::LAT_LNG->value] ?? [],
            is_array(...),
        ));

        if ([] === $coordinates) {
            return null;
        }

        return (string) Polyline::fromLatLngCoordinates($coordinates)->simplify()->encode();
    }

    /**
     * @param array<string, list<mixed>> $streams
     */
    public static function firstCoordinate(array $streams): ?Coordinate
    {
        foreach ($streams[StreamType::LAT_LNG->value] ?? [] as $point) {
            if (is_array($point)) {
                return Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString((string) $point[0]),
                    longitude: Longitude::fromString((string) $point[1]),
                );
            }
        }

        return null;
    }

    /**
     * @param list<?float> $distances
     * @param list<?int>   $times
     *
     * @return list<?float>
     */
    public static function deriveVelocityStream(array $distances, array $times): array
    {
        $velocities = [];
        $previousDistance = null;
        $previousTime = null;

        foreach ($distances as $index => $distance) {
            $time = $times[$index] ?? null;
            if (null === $distance || null === $time) {
                $velocities[] = null;
                continue;
            }

            if (null !== $previousDistance && null !== $previousTime && $time > $previousTime) {
                $velocities[] = ($distance - $previousDistance) / ($time - $previousTime);
            } else {
                $velocities[] = null;
            }

            $previousDistance = $distance;
            $previousTime = $time;
        }

        return $velocities;
    }

    /**
     * @param list<array{float, float}|null> $latLng
     *
     * @return list<?float>
     */
    public static function deriveDistanceStream(array $latLng): array
    {
        $distances = [];
        $cumulativeDistance = 0.0;
        $previous = null;

        foreach ($latLng as $point) {
            if (null !== $previous && null !== $point) {
                $cumulativeDistance += GeoMath::haversineDistance(
                    lat1: $previous[0],
                    lon1: $previous[1],
                    lat2: $point[0],
                    lon2: $point[1],
                );
            }
            $distances[] = round($cumulativeDistance, 2);
            $previous = $point ?? $previous;
        }

        return $distances;
    }
}
