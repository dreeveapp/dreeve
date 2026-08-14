<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Infrastructure\ValueObject\Geography\BoundingBox;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\Geography\Polyline;

final readonly class RouteGeographyAnalyzer
{
    public function __construct(
        private CountryGeographyAssets $assets,
    ) {
    }

    /**
     * @return string[]
     */
    public function analyzeForPolyline(EncodedPolyline $polyline): array
    {
        $latLngCoordinates = Polyline::fromEncodedPolyline($polyline)
            ->sanitize()
            ->densify()
            ->getLatLngCoordinates();

        if (count($latLngCoordinates) < 2) {
            return [];
        }

        // The country assets are GeoJSON, which is [lng, lat].
        $coordinates = array_map(fn (array $pair): array => [$pair[1], $pair[0]], $latLngCoordinates);
        $routeBounds = BoundingBox::fromLngLatPairs($coordinates)->widenedWhenSpanningTheAntimeridian();

        /** @var array<string, list<int>> $candidates */
        $candidates = [];
        foreach ($this->assets->index() as $entry) {
            // Bounding box overlap is a necessary condition for intersection.
            if (!$routeBounds->overlaps($entry['boundingBox'])) {
                continue;
            }
            $candidates[$entry['countryCode']][] = $entry['polygonIndex'];
        }

        $passedCountries = [];
        foreach ($candidates as $countryCode => $polygonIndexes) {
            $polygons = $this->assets->polygonsFor($countryCode);

            foreach ($polygonIndexes as $polygonIndex) {
                if (!$rings = $polygons[$polygonIndex] ?? null) {
                    continue; // @codeCoverageIgnore
                }
                $pruned = Polygon::fromLngLatRings($rings)->pruned($routeBounds);
                if (!$pruned?->containsAnyOf($coordinates)) {
                    continue;
                }

                $passedCountries[] = $countryCode;
                // No need to test this country's remaining polygons.
                break;
            }
        }

        sort($passedCountries);

        return $passedCountries;
    }
}
