<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\MapsSettings;
use App\Infrastructure\ValueObject\String\Url;
use PHPUnit\Framework\TestCase;

class MapsSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = MapsSettings::fromArray([]);

        $this->assertSame('#fc6719', (string) $settings->getLeafletConfig()->getPolylineColor());
        $this->assertSame(
            ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            array_map(strval(...), $settings->getLeafletConfig()->getTileLayerUrls()),
        );
        $this->assertTrue($settings->getLeafletConfig()->enableGreyScale());
        $this->assertTrue($settings->getLeafletConfig()->requireCtrlToZoom());
        $this->assertNull($settings->getHeatmapConfig()->getInitialCenter());
        $this->assertSame(12, $settings->getHeatmapConfig()->getInitialZoom()?->getValue());
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = MapsSettings::fromArray([
            'polylineColor' => '#000000',
            'tileLayerUrl' => [
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png',
                'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}.png',
            ],
            'enableGreyScale' => false,
            'requireCtrlToZoom' => false,
            'heatmap' => [
                'initialCenter' => [51.0, 3.7],
                'initialZoom' => 8,
            ],
        ]);

        $this->assertSame('#000000', (string) $settings->getLeafletConfig()->getPolylineColor());
        $this->assertCount(2, $settings->getLeafletConfig()->getTileLayerUrls());
        $this->assertFalse($settings->getLeafletConfig()->enableGreyScale());
        $this->assertFalse($settings->getLeafletConfig()->requireCtrlToZoom());
        $this->assertSame(51.0, $settings->getHeatmapConfig()->getInitialCenter()?->getLatitude()->toFloat());
        $this->assertSame(3.7, $settings->getHeatmapConfig()->getInitialCenter()?->getLongitude()->toFloat());
        $this->assertSame(8, $settings->getHeatmapConfig()->getInitialZoom()?->getValue());
    }

    public function testItDropsBlankTileLayerRowsSubmittedByTheAdminForm(): void
    {
        $settings = MapsSettings::fromArray(['tileLayerUrl' => ['https://tile.example.com/{z}/{x}/{y}.png', '']]);

        $this->assertSame(
            ['https://tile.example.com/{z}/{x}/{y}.png'],
            array_map(strval(...), $settings->getLeafletConfig()->getTileLayerUrls()),
        );
    }

    public function testItThrowsForAWhitespaceOnlyTileLayerRow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(Url::class.' can not be empty'));

        MapsSettings::fromArray(['tileLayerUrl' => ['   ']]);
    }

    public function testItFallsBackToTheDefaultTileLayerWhenTheListIsEmptied(): void
    {
        $settings = MapsSettings::fromArray(['tileLayerUrl' => ['']]);

        $this->assertSame(
            ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            array_map(strval(...), $settings->getLeafletConfig()->getTileLayerUrls()),
        );
    }

    public function testItReadsTheGreyScaleCheckboxSubmittedAsAString(): void
    {
        $this->assertFalse(MapsSettings::fromArray(['enableGreyScale' => '0'])->getLeafletConfig()->enableGreyScale());
        $this->assertTrue(MapsSettings::fromArray(['enableGreyScale' => '1'])->getLeafletConfig()->enableGreyScale());
    }

    public function testItReadsTheCtrlToZoomCheckboxSubmittedAsAString(): void
    {
        $this->assertFalse(MapsSettings::fromArray(['requireCtrlToZoom' => '0'])->getLeafletConfig()->requireCtrlToZoom());
        $this->assertTrue(MapsSettings::fromArray(['requireCtrlToZoom' => '1'])->getLeafletConfig()->requireCtrlToZoom());
    }

    public function testItHasNoInitialCenterWhenTheAdminFormSubmitsBlankCoordinates(): void
    {
        $settings = MapsSettings::fromArray(['heatmap' => ['initialCenter' => ['', ''], 'initialZoom' => '']]);

        $this->assertNull($settings->getHeatmapConfig()->getInitialCenter());
        $this->assertSame(12, $settings->getHeatmapConfig()->getInitialZoom()?->getValue());
    }

    public function testItHasNoInitialCenterWhenOnlyTheLatitudeIsSubmitted(): void
    {
        $settings = MapsSettings::fromArray(['heatmap' => ['initialCenter' => ['51.0', '']]]);

        $this->assertNull($settings->getHeatmapConfig()->getInitialCenter());
    }

    public function testItBuildsTheInitialCenterFromCoordinatesSubmittedAsStrings(): void
    {
        $settings = MapsSettings::fromArray(['heatmap' => ['initialCenter' => ['51.05', '3.72'], 'initialZoom' => '14']]);

        $this->assertSame(51.05, $settings->getHeatmapConfig()->getInitialCenter()?->getLatitude()->toFloat());
        $this->assertSame(3.72, $settings->getHeatmapConfig()->getInitialCenter()?->getLongitude()->toFloat());
        $this->assertSame(14, $settings->getHeatmapConfig()->getInitialZoom()?->getValue());
    }

    public function testItThrowsForAnInvalidPolylineColor(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('notacolour is not a valid CSS color'));

        MapsSettings::fromArray(['polylineColor' => 'notacolour']);
    }

    public function testItThrowsForAnInvalidTileLayerUrl(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid url "not-a-url"'));

        MapsSettings::fromArray(['tileLayerUrl' => ['not-a-url']]);
    }

    public function testItThrowsForAZoomLevelOutOfRange(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('ZoomLevel must be a number between 1 and 18, got 19'));

        MapsSettings::fromArray(['heatmap' => ['initialZoom' => '19']]);
    }
}
