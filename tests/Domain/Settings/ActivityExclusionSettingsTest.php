<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\ActivityId;
use App\Domain\Settings\ActivityExclusionSettings;
use PHPUnit\Framework\TestCase;

class ActivityExclusionSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = ActivityExclusionSettings::fromArray(null);

        $this->assertCount(0, $settings->getExcludedFromPeakPowerOutputs());
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = ActivityExclusionSettings::fromArray([
            'excludedFromPeakPowerOutputs' => ['activity-15320954660', '  activity-42  ', ''],
        ]);

        $this->assertEquals(
            [ActivityId::fromUnprefixed('15320954660'), ActivityId::fromUnprefixed('42')],
            $settings->getExcludedFromPeakPowerOutputs()->toArray(),
        );
    }

    public function testItThrowsForAnUnprefixedActivityId(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Identifier does not start with prefix "activity-", got: 15320954660'));

        ActivityExclusionSettings::fromArray([
            'excludedFromPeakPowerOutputs' => ['15320954660'],
        ]);
    }
}
