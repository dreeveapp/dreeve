<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Config\Leaflet\BasemapPreset;
use App\Infrastructure\Twig\EnumTwigExtension;
use App\Tests\ContainerTestCase;

class EnumTwigExtensionTest extends ContainerTestCase
{
    private EnumTwigExtension $enumTwigExtension;

    public function testGetSportTypeOptions(): void
    {
        $options = $this->enumTwigExtension->getSportTypeOptions();

        $this->assertCount(count(SportType::cases()), $options);
        foreach (SportType::cases() as $index => $sportType) {
            $this->assertSame($sportType->value, $options[$index]['value']);
            $this->assertNotEmpty($options[$index]['label']);
        }
    }

    public function testGetActivityTypeFrom(): void
    {
        $this->assertSame(
            ActivityType::RIDE,
            $this->enumTwigExtension->getActivityTypeFrom(ActivityType::RIDE->value)
        );
    }

    public function testGetActivityTypeFromItShouldThrowOnInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        $this->enumTwigExtension->getActivityTypeFrom('lol');
    }

    public function testGetBasemapPresetForUrls(): void
    {
        $this->assertSame(
            BasemapPreset::CARTO_DARK_MATTER,
            $this->enumTwigExtension->getBasemapPresetForUrls(BasemapPreset::CARTO_DARK_MATTER->getTileLayerUrls()),
        );

        $this->assertNull($this->enumTwigExtension->getBasemapPresetForUrls(['https://example.com/{z}/{x}/{y}.png']));
        $this->assertNull($this->enumTwigExtension->getBasemapPresetForUrls('not-an-array'));
    }

    public function testGetEnumTileLayerUrls(): void
    {
        $urls = $this->enumTwigExtension->getEnumTileLayerUrls();

        $this->assertCount(count(BasemapPreset::cases()), $urls);
        foreach (BasemapPreset::cases() as $preset) {
            $this->assertSame($preset->getTileLayerUrls(), $urls[$preset->value]);
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->enumTwigExtension = $this->getContainer()->get(EnumTwigExtension::class);
    }
}
