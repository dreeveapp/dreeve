<?php

namespace App\Tests\Domain\Activity\Route;

use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class CountriesGeographyAssetTest extends TestCase
{
    private const string ASSETS_DIRECTORY = __DIR__.'/../../../../src/Domain/Activity/Route/assets/countries';

    public function testEveryIndexEntryIsWellFormed(): void
    {
        foreach ($this->index() as $entry) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $entry['countryCode']);
            $this->assertFileExists(sprintf('%s/%s.json', self::ASSETS_DIRECTORY, $entry['countryCode']));

            [$minLng, $minLat, $maxLng, $maxLat] = $entry['boundingBox'];
            $this->assertLessThanOrEqual($maxLng, $minLng);
            $this->assertLessThanOrEqual($maxLat, $minLat);
            $this->assertGreaterThanOrEqual(-180, $minLng);
            $this->assertLessThanOrEqual(180, $maxLng);
            $this->assertGreaterThanOrEqual(-90, $minLat);
            $this->assertLessThanOrEqual(90, $maxLat);
        }
    }

    public function testEveryBoundingBoxMatchesItsPolygon(): void
    {
        $polygonsPerCountry = [];

        foreach ($this->index() as $entry) {
            $polygons = $polygonsPerCountry[$entry['countryCode']] ??= Json::decode(
                file_get_contents(sprintf('%s/%s.json', self::ASSETS_DIRECTORY, $entry['countryCode'])) ?: '[]'
            );

            $exteriorRing = $polygons[$entry['polygonIndex']][0] ?? null;
            $this->assertNotNull($exteriorRing);

            $this->assertEquals(
                [
                    min(array_column($exteriorRing, 0)),
                    min(array_column($exteriorRing, 1)),
                    max(array_column($exteriorRing, 0)),
                    max(array_column($exteriorRing, 1)),
                ],
                $entry['boundingBox']
            );
        }
    }

    public function testSmallCountriesSurvivedTheBuild(): void
    {
        $countryCodes = array_unique(array_column($this->index(), 'countryCode'));

        foreach (['BE', 'NL', 'CZ', 'PL', 'MC', 'LI', 'SM', 'VA', 'AD', 'LS'] as $countryCode) {
            $this->assertContains($countryCode, $countryCodes);
        }
    }

    /**
     * @return array<int, array{countryCode: string, polygonIndex: int, boundingBox: array{float, float, float, float}}>
     */
    private function index(): array
    {
        return Json::decode(file_get_contents(self::ASSETS_DIRECTORY.'/index.json') ?: '[]');
    }
}
