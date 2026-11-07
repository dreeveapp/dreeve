<?php

namespace App\Tests\Domain\Geography\Country;

use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class CountriesGeographyAssetTest extends TestCase
{
    private const string ASSETS_DIRECTORY = __DIR__.'/../../../../resources/countries';

    public function testEveryIndexEntryIsWellFormed(): void
    {
        foreach (Json::uncompressAndDecode((string) file_get_contents(self::ASSETS_DIRECTORY.'/index.json.zst')) as $entry) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $entry['countryCode']);
            $this->assertFileExists(sprintf('%s/%s.json.zst', self::ASSETS_DIRECTORY, $entry['countryCode']));

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

        foreach (Json::uncompressAndDecode((string) file_get_contents(self::ASSETS_DIRECTORY.'/index.json.zst')) as $entry) {
            $polygons = $polygonsPerCountry[$entry['countryCode']] ??= Json::uncompressAndDecode(
                (string) file_get_contents(sprintf('%s/%s.json.zst', self::ASSETS_DIRECTORY, $entry['countryCode']))
            );

            $exteriorRing = $polygons[$entry['polygonIndex']][0] ?? null;
            $this->assertNotNull($exteriorRing);
            $longitudes = array_column($exteriorRing, 0);
            $latitudes = array_column($exteriorRing, 1);
            $this->assertNotEmpty($longitudes);
            $this->assertNotEmpty($latitudes);

            $this->assertEquals(
                [
                    min($longitudes),
                    min($latitudes),
                    max($longitudes),
                    max($latitudes),
                ],
                $entry['boundingBox']
            );
        }
    }

    public function testSmallCountriesSurvivedTheBuild(): void
    {
        $countryCodes = array_unique(array_column(Json::uncompressAndDecode((string) file_get_contents(self::ASSETS_DIRECTORY.'/index.json.zst')), 'countryCode'));

        foreach (['BE', 'NL', 'CZ', 'PL', 'MC', 'LI', 'SM', 'VA', 'AD', 'LS'] as $countryCode) {
            $this->assertContains($countryCode, $countryCodes);
        }
    }
}
