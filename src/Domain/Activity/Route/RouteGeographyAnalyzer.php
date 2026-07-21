<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;
use Brick\Geo\Engine\GeosOpEngine;
use Brick\Geo\Exception\InvalidGeometryException;
use Brick\Geo\Geometry;
use Brick\Geo\Io\GeoJson\Feature;
use Brick\Geo\Io\GeoJson\FeatureCollection;
use Brick\Geo\Io\GeoJsonReader;

final readonly class RouteGeographyAnalyzer
{
    /**
     * Tolerance (in degrees, ~1.1km) used to simplify the route BEFORE it is handed to
     * geosop. The stored polyline is kept high-fidelity for the heatmap, but geosop
     * receives the route's WKT as a single command-line argument. A dense, high-frequency
     * route can produce a WKT larger than the OS MAX_ARG_STRLEN (~128KB), which crashes
     * the import with a GeometryEngineException ("Failed to run geosop") (#2230).
     * Simplifying the geosop input decouples it from the stored polyline: coarse enough to
     * keep the argument bounded, fine enough to preserve country-level intersections.
     */
    private const float GEOSOP_SIMPLIFY_TOLERANCE = 0.01;

    private GeosOpEngine $engine;
    private GeoJsonReader $reader;
    /** @var array<string, Geometry|Feature|FeatureCollection> */
    private array $countriesGeometry;

    public function __construct()
    {
        $this->engine = new GeosOpEngine('/usr/bin/geosop');
        $this->reader = new GeoJsonReader();
        $this->countriesGeometry = $this->buildCountriesGeometry();
    }

    /**
     * @return array<string, Geometry|Feature|FeatureCollection>
     */
    private function buildCountriesGeometry(): array
    {
        $countriesGeometry = [];
        $rawCountriesGeoJson = Json::decode(file_get_contents(__DIR__.'/assets/countries-geography.json') ?: '{}');

        foreach ($rawCountriesGeoJson['features'] ?? [] as $feature) {
            if (!isset($feature['properties']['ISO_A2_EH'])) {
                continue; // @codeCoverageIgnore
            }
            $countryCode = $feature['properties']['ISO_A2_EH'];

            $countriesGeometry[$countryCode] = $this->reader->read(Json::encode([
                'type' => $feature['geometry']['type'],
                'coordinates' => $feature['geometry']['coordinates'],
            ]));
        }

        return $countriesGeometry;
    }

    /**
     * @return string[]
     */
    public function analyzeForPolyline(EncodedPolyline $polyline): array
    {
        $passedCountries = [];

        // Aggressively simplify the route on its own before feeding it to geosop, so the
        // WKT argument stays bounded regardless of how dense the stored polyline is (#2230).
        /** @var array<int, array{float, float}> $coordinates */
        $coordinates = $polyline->decodeAndPairLatLng();
        $simplifiedPolyline = Polyline::fromCoordinates($coordinates)
            ->simplify(self::GEOSOP_SIMPLIFY_TOLERANCE)
            ->encode();

        try {
            $routeLineString = $this->reader->read(Json::encode([
                'type' => 'LineString',
                'coordinates' => $simplifiedPolyline->decodeAndPairLngLat(),
            ]));
        } catch (InvalidGeometryException) {
            // Given polyline is somehow not a valid LineString.
            return $passedCountries;
        }

        foreach ($this->countriesGeometry as $countryCode => $countryGeometry) {
            if (!$countryGeometry instanceof Geometry) {
                continue; // @codeCoverageIgnore
            }
            if (!$routeLineString instanceof Geometry) {
                continue; // @codeCoverageIgnore
            }
            if (!$this->engine->intersects($countryGeometry, $routeLineString)) {
                continue;
            }
            $passedCountries[$countryCode] = $countryCode;
        }

        return array_values($passedCountries);
    }
}
