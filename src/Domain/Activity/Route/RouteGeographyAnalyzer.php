<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\BoundingBox;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\Geography\Polyline;

final class RouteGeographyAnalyzer
{
    /** @var list<array{countryCode: string, polygonIndex: int, boundingBox: BoundingBox}>|null */
    private ?array $index = null;

    private readonly string $assetsDirectory;

    public function __construct()
    {
        $this->assetsDirectory = __DIR__.'/assets/countries';
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
        foreach ($this->index() as $entry) {
            // Bounding box overlap is a necessary condition for intersection.
            if (!$routeBounds->overlaps($entry['boundingBox'])) {
                continue;
            }
            $candidates[$entry['countryCode']][] = $entry['polygonIndex'];
        }

        $passedCountries = [];
        foreach ($candidates as $countryCode => $polygonIndexes) {
            $polygons = $this->polygonsFor($countryCode);

            foreach ($polygonIndexes as $polygonIndex) {
                if (!$rings = $polygons[$polygonIndex] ?? null) {
                    continue;
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

    /**
     * @return list<array{countryCode: string, polygonIndex: int, boundingBox: BoundingBox}>
     */
    private function index(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        /** @var list<array{countryCode: string, polygonIndex: int, boundingBox: array{float, float, float, float}}> $rawIndex */
        $rawIndex = Json::decode($this->readAsset('index.json'));

        return $this->index = array_map(
            fn (array $entry): array => [
                ...$entry,
                'boundingBox' => BoundingBox::fromArray($entry['boundingBox']),
            ],
            $rawIndex
        );
    }

    /**
     * @return array<int, list<list<array{float, float}>>>
     */
    private function polygonsFor(string $countryCode): array
    {
        return Json::decode($this->readAsset($countryCode.'.json'));
    }

    private function readAsset(string $fileName): string
    {
        $path = $this->assetsDirectory.'/'.$fileName;

        if (!is_file($path) || false === $contents = file_get_contents($path)) {
            throw new \RuntimeException(sprintf(  /* @codeCoverageIgnore */ 'Country boundary asset "%s" is missing or unreadable. Rebuild it with "make build-countries-asset".', $path));
        }

        return $contents;
    }
}
