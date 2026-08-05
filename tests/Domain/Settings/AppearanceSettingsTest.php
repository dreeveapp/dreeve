<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\AppearanceSettings;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Time\Format\TimeFormat;
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
        ]);

        $this->assertSame(UnitSystem::IMPERIAL, $settings->getUnitSystem());
        $this->assertSame(Locale::nl_BE, $settings->getLocale());
        $this->assertSame(TimeFormat::AM_PM, $settings->getDateAndTimeFormat()->getTimeFormat());
        $this->assertSame('m-d-y', (string) $settings->getDateAndTimeFormat()->getDateFormatShort());
        $this->assertCount(1, $settings->getHidePhotosForSportTypes());
    }

    public function testItThrowsForAnInvalidDateFormat(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date format provided "q", invalid format characters found: q'));

        AppearanceSettings::fromArray(['dateFormat' => ['short' => 'q', 'normal' => 'q']]);
    }
}
