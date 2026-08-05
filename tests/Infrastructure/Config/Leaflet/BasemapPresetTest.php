<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config\Leaflet;

use App\Infrastructure\Config\Leaflet\BasemapPreset;
use App\Infrastructure\Config\Leaflet\TileLayerUrl;
use PHPUnit\Framework\TestCase;

class BasemapPresetTest extends TestCase
{
    public function testEveryPresetYieldsValidTileLayerUrls(): void
    {
        foreach (BasemapPreset::cases() as $preset) {
            $urls = $preset->getTileLayerUrls();

            $this->assertNotEmpty($urls, sprintf('Preset "%s" has no tile layer urls', $preset->value));

            foreach ($urls as $url) {
                // Throws when the url is malformed or missing {z}/{x}/{y}.
                $this->assertNotEmpty((string) TileLayerUrl::fromString($url));
            }
        }
    }

    public function testTheOpenStreetMapPresetMatchesTheApplicationDefault(): void
    {
        $this->assertSame(
            ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            BasemapPreset::OPEN_STREET_MAP->getTileLayerUrls(),
        );
    }

    public function testTheSatellitePresetIsMultiLayer(): void
    {
        $this->assertCount(2, BasemapPreset::ESRI_WORLD_IMAGERY->getTileLayerUrls());
    }

    public function testItResolvesAPresetFromStoredUrls(): void
    {
        $this->assertSame(
            BasemapPreset::CARTO_DARK_MATTER,
            BasemapPreset::tryFromTileLayerUrls(BasemapPreset::CARTO_DARK_MATTER->getTileLayerUrls()),
        );
    }

    public function testItResolvesToNullForCustomUrls(): void
    {
        $this->assertNull(BasemapPreset::tryFromTileLayerUrls(['https://example.com/{z}/{x}/{y}.png']));
    }

    public function testItResolvesToNullForAPartialMatch(): void
    {
        $urls = BasemapPreset::ESRI_WORLD_IMAGERY->getTileLayerUrls();

        $this->assertNull(BasemapPreset::tryFromTileLayerUrls([$urls[0]]));
    }

    public function testEveryPresetHasALabel(): void
    {
        foreach (BasemapPreset::cases() as $preset) {
            $this->assertNotEmpty($preset->getLabel());
        }
    }
}
