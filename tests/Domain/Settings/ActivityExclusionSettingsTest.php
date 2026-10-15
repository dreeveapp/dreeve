<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\ActivityId;
use App\Domain\Settings\ActivityExclusion;
use App\Domain\Settings\ActivityExclusionSettings;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use PHPUnit\Framework\TestCase;

class ActivityExclusionSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = ActivityExclusionSettings::fromArray(null);

        foreach (ActivityExclusion::cases() as $exclusion) {
            $this->assertCount(0, $settings->getExcludedActivityIds($exclusion));
        }
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = ActivityExclusionSettings::fromArray([
            'excludedFromPeakPowerOutputs' => ['activity-15320954660', '  activity-42  ', ''],
        ]);

        $this->assertEquals(
            [ActivityId::fromUnprefixed('15320954660'), ActivityId::fromUnprefixed('42')],
            $settings->getExcludedActivityIds(ActivityExclusion::PEAK_POWER_OUTPUTS)->toArray(),
        );
    }

    public function testEveryExclusionIsStoredUnderItsOwnSettingInTheActivityExclusionsGroup(): void
    {
        $settingsNames = array_map(
            fn (ActivityExclusion $exclusion): SettingsName => $exclusion->getSettingsName(),
            ActivityExclusion::cases(),
        );

        $this->assertCount(count(ActivityExclusion::cases()), array_unique(array_map(fn (SettingsName $name): string => $name->value, $settingsNames)));
        foreach ($settingsNames as $settingsName) {
            $this->assertSame(SettingsGroup::ACTIVITY_EXCLUSIONS, $settingsName->group());
        }
    }

    public function testItThrowsForAnUnprefixedActivityId(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Identifier does not start with prefix "activity-", got: 15320954660'));

        ActivityExclusionSettings::fromArray([
            'excludedFromPeakPowerOutputs' => ['15320954660'],
        ]);
    }
}
