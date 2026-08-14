<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Heatmap;

use App\Domain\Activity\Route\CountryGeographyAssets;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\Geography\Polyline;

final readonly class CountryBoundaries
{

    private const float SIMPLIFY_TOLERANCE_IN_DEGREES = 0.01;
    private const float ISLET_THRESHOLD_RATIO = 0.01;
    private const int COORDINATE_PRECISION = 4;

    public function __construct(
        private CountryGeographyAssets $assets,
    ) {
    }

    /**
     * @param string[] $countryCodes
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function geoJsonFor(array $countryCodes): array
    {
        $countryCodes = array_unique(array_map(strtoupper(...), $countryCodes));
        sort($countryCodes);

        $features = [];
        foreach ($countryCodes as $countryCode) {
            if (!$this->assets->has($countryCode)) {
                continue;
            }

            if (!$polygons = $this->simplifiedPolygonsFor($countryCode)) {
                continue; // @codeCoverageIgnore
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => ['countryCode' => $countryCode],
                'geometry' => [
                    'type' => 'MultiPolygon',
                    'coordinates' => $polygons,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * @return list<list<list<array{float, float}>>>
     */
    private function simplifiedPolygonsFor(string $countryCode): array
    {
        $polygons = array_values($this->assets->polygonsFor($countryCode));

        $diagonals = array_map(
            fn (array $rings): float => Polygon::fromLngLatRings($rings)->boundingBox()->diagonalInDegrees(),
            $polygons
        );
        if (!$diagonals) {
            return []; // @codeCoverageIgnore
        }
        $largest = max($diagonals);
        $threshold = $largest * self::ISLET_THRESHOLD_RATIO;

        $simplified = [];
        foreach ($polygons as $polygonIndex => $rings) {
            if ($diagonals[$polygonIndex] < $threshold && $diagonals[$polygonIndex] < $largest) {
                continue;
            }

            $simplifiedRings = [];
            foreach ($rings as $ringIndex => $ring) {
                if ($ringIndex > 0 && Polygon::fromLngLatRings([$ring])->boundingBox()->diagonalInDegrees() < $threshold) {
                    continue;
                }
                $simplifiedRings[] = $this->simplifyRing($ring);
            }

            if ($simplifiedRings) {
                $simplified[] = $simplifiedRings;
            }
        }

        return $simplified;
    }

    /**
     * @param list<array{float, float}> $ring
     *
     * @return list<array{float, float}>
     */
    private function simplifyRing(array $ring): array
    {
        $ring = array_map(
            fn (array $coordinate): array => [(float) $coordinate[0], (float) $coordinate[1]],
            $ring
        );

        $simplified = Polyline::fromLatLngCoordinates($ring)
            ->simplify(self::SIMPLIFY_TOLERANCE_IN_DEGREES)
            ->getLatLngCoordinates();

        if (count($simplified) < 4) {
            $simplified = $ring;
        }

        return array_map(
            fn (array $coordinate): array => [
                round($coordinate[0], self::COORDINATE_PRECISION),
                round($coordinate[1], self::COORDINATE_PRECISION),
            ],
            $simplified
        );
    }
}
