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
        try {
            $coordinates = $polyline->decodeAndPairLatLng();
            $simplifiedPolyline = Polyline::fromCoordinates($coordinates)
                ->simplify(0.005)
                ->encode();

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
