<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\WidgetName;
use PHPUnit\Framework\TestCase;

class WidgetNameTest extends TestCase
{
    public function testActivityIntensityBackwardsCompatibility(): void
    {
        $this->assertEquals(
            'activityGrid',
            (string) WidgetName::fromConfigValue('activityIntensity')
        );
    }

    public function testYearlyDistancesBackwardsCompatibility(): void
    {
        $this->assertEquals(
            'yearlyStats',
            (string) WidgetName::fromConfigValue('yearlyDistances')
        );
    }

    public function testMostRecentActivitiesWithMapBackwardsCompatibility(): void
    {
        $this->assertEquals(
            'mostRecentActivityCards',
            (string) WidgetName::fromConfigValue('mostRecentActivitiesWithMap')
        );
    }
}
