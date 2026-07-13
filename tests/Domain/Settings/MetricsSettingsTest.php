<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\Eddington\InvalidEddingtonConfiguration;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Settings\MetricsSettings;
use PHPUnit\Framework\TestCase;

class MetricsSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = MetricsSettings::fromArray([]);

        // An empty eddington configuration falls back to the built-in default config.
        $this->assertGreaterThan(0, count($settings->getEddingtonConfiguration()));
        $this->assertCount(0, $settings->getActivitiesExcludedFromPeakPowerOutputs());
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = MetricsSettings::fromArray([
            'excludeActivitiesFromPeakPowerOutputs' => [15320954660, '  42  ', ''],
            'eddington' => [
                [
                    'label' => 'Ride',
                    'showInNavBar' => true,
                    'showInDashboardWidget' => false,
                    'sportTypesToInclude' => ['Ride', 'VirtualRide'],
                ],
            ],
        ]);

        $eddingtonConfiguration = $settings->getEddingtonConfiguration();
        $this->assertCount(1, $eddingtonConfiguration);
        $item = $eddingtonConfiguration->getFirst();
        $this->assertSame('Ride', $item->getLabel());
        $this->assertTrue($item->showInNavBar());
        $this->assertFalse($item->showInDashboardWidget());
        $this->assertTrue($item->getSportTypesToInclude()->has(SportType::RIDE));

        // Empty/whitespace-only ids are filtered out, the rest are trimmed.
        $this->assertCount(2, $settings->getActivitiesExcludedFromPeakPowerOutputs());
    }

    public function testItNormalizesCheckboxBooleansFromFormPayload(): void
    {
        // Unchecked checkboxes are simply absent from the payload; the string "1"
        // is what a checked checkbox submits.
        $settings = MetricsSettings::fromArray([
            'eddington' => [
                [
                    'label' => 'Ride',
                    'showInNavBar' => '1',
                    'sportTypesToInclude' => ['Ride'],
                ],
            ],
        ]);

        $item = $settings->getEddingtonConfiguration()->getFirst();
        $this->assertTrue($item->showInNavBar());
        $this->assertFalse($item->showInDashboardWidget());
    }

    public function testItThrowsForAnInvalidSportType(): void
    {
        $this->expectExceptionObject(new InvalidEddingtonConfiguration('"NotASportType" is not a valid sport type'));

        MetricsSettings::fromArray([
            'eddington' => [
                [
                    'label' => 'Ride',
                    'showInNavBar' => true,
                    'sportTypesToInclude' => ['NotASportType'],
                ],
            ],
        ]);
    }
}
