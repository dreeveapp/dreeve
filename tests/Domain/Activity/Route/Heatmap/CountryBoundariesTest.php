<?php

namespace App\Tests\Domain\Activity\Route\Heatmap;

use App\Domain\Activity\Route\CountryGeographyAssets;
use App\Domain\Activity\Route\Heatmap\CountryBoundaries;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CountryBoundariesTest extends TestCase
{
    private CountryBoundaries $countryBoundaries;

    public function testItAcceptsTheLowercaseCodesTheRoutesCarry(): void
    {
        $features = $this->countryBoundaries->geoJsonFor(['be'])['features'];

        $this->assertCount(1, $features);
        $this->assertEquals('BE', $features[0]['properties']['countryCode']);
        $this->assertEquals('MultiPolygon', $features[0]['geometry']['type']);
    }

    public function testItSkipsCountriesWithoutABoundaryAsset(): void
    {
        $this->assertEmpty($this->countryBoundaries->geoJsonFor(['zz'])['features']);
    }

    public function testItSortsFeaturesByCountryCode(): void
    {
        $this->assertEquals(
            ['BE', 'FR', 'NL'],
            array_column(array_column($this->countryBoundaries->geoJsonFor(['nl', 'be', 'fr'])['features'], 'properties'), 'countryCode')
        );
    }

    public function testItDeduplicatesCountryCodes(): void
    {
        $this->assertCount(1, $this->countryBoundaries->geoJsonFor(['be', 'BE'])['features']);
    }

    #[TestWith(['va'])]
    #[TestWith(['mc'])]
    #[TestWith(['sm'])]
    #[TestWith(['li'])]
    public function testItKeepsMicrostatesThatAreSmallerThanTheSimplifyTolerance(string $countryCode): void
    {
        $features = $this->countryBoundaries->geoJsonFor([$countryCode])['features'];

        $this->assertCount(1, $features);
        $this->assertGreaterThanOrEqual(4, $this->countVertices($features[0]));
    }

    public function testItDropsIslets(): void
    {
        $raw = new CountryGeographyAssets()->polygonsFor('FR');
        $features = $this->countryBoundaries->geoJsonFor(['fr'])['features'];

        $this->assertLessThan(count($raw), count($features[0]['geometry']['coordinates']));

        // Clipperton Island sits alone in the Pacific, thousands of kilometres from the mainland.
        foreach ($features[0]['geometry']['coordinates'] as $polygon) {
            foreach ($polygon as $ring) {
                foreach ($ring as [$longitude]) {
                    $this->assertGreaterThan(-100, $longitude);
                }
            }
        }
    }

    public function testItSimplifiesGeometry(): void
    {
        $features = $this->countryBoundaries->geoJsonFor(['be'])['features'];

        $this->assertLessThan(400, $this->countVertices($features[0]));
    }

    public function testEveryRingIsClosedAndWithinRange(): void
    {
        foreach ($this->countryBoundaries->geoJsonFor(['be', 'nl', 'fr', 'it', 'ru'])['features'] as $feature) {
            foreach ($feature['geometry']['coordinates'] as $polygon) {
                foreach ($polygon as $ring) {
                    $this->assertGreaterThanOrEqual(4, count($ring));
                    $this->assertEquals($ring[0], $ring[count($ring) - 1]);
                    foreach ($ring as [$longitude, $latitude]) {
                        $this->assertLessThanOrEqual(180, abs($longitude));
                        $this->assertLessThanOrEqual(90, abs($latitude));
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function countVertices(array $feature): int
    {
        $total = 0;
        foreach ($feature['geometry']['coordinates'] as $polygon) {
            foreach ($polygon as $ring) {
                $total += count($ring);
            }
        }

        return $total;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryBoundaries = new CountryBoundaries(new CountryGeographyAssets());
    }
}
