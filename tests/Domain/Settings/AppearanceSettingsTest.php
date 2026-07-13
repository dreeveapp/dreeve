<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\AppearanceSettings;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\Time\Format\TimeFormat;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use PHPUnit\Framework\TestCase;

class AppearanceSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = AppearanceSettings::fromArray([]);

        $this->assertSame(UnitSystem::METRIC, $settings->getUnitSystem());
        $this->assertSame(Locale::en_US, $settings->getLocale());
        $this->assertSame(TimeFormat::TWENTY_FOUR, $settings->getDateAndTimeFormat()->getTimeFormat());
        $this->assertSame('d-m-y', (string) $settings->getDateAndTimeFormat()->getDateFormatShort());
        $this->assertSame('d-m-Y', (string) $settings->getDateAndTimeFormat()->getDateFormatNormal());
        $this->assertCount(0, $settings->getHidePhotosForSportTypes());
        $this->assertSame('#fc6719', (string) $settings->getLeafletConfig()->getPolylineColor());
        $this->assertTrue($settings->getLeafletConfig()->enableGreyScale());
        $this->assertNull($settings->getHeatmapConfig()->getInitialCenter());
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = AppearanceSettings::fromArray([
            'unitSystem' => 'imperial',
            'locale' => 'nl_BE',
            'timeFormat' => 12,
            'dateFormat' => [
                'short' => 'm-d-y',
                'normal' => 'm-d-Y',
            ],
            'photos' => [
                'hidePhotosForSportTypes' => ['VirtualRide'],
            ],
            'maps' => [
                'polylineColor' => '#000000',
                'enableGreyScale' => false,
                'heatmap' => [
                    'initialCenter' => [51.0, 3.7],
                    'initialZoom' => 8,
                ],
            ],
        ]);

        $this->assertSame(UnitSystem::IMPERIAL, $settings->getUnitSystem());
        $this->assertSame(Locale::nl_BE, $settings->getLocale());
        $this->assertSame(TimeFormat::AM_PM, $settings->getDateAndTimeFormat()->getTimeFormat());
        $this->assertSame('m-d-y', (string) $settings->getDateAndTimeFormat()->getDateFormatShort());
        $this->assertCount(1, $settings->getHidePhotosForSportTypes());
        $this->assertSame('#000000', (string) $settings->getLeafletConfig()->getPolylineColor());
        $this->assertFalse($settings->getLeafletConfig()->enableGreyScale());
        $this->assertNotNull($settings->getHeatmapConfig()->getInitialCenter());
        $this->assertSame(8, $settings->getHeatmapConfig()->getInitialZoom()?->getValue());
    }

    public function testItThrowsForAnInvalidDateFormat(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date format provided "q", invalid format characters found: q'));

        AppearanceSettings::fromArray(['dateFormat' => ['short' => 'q', 'normal' => 'q']]);
    }
}
