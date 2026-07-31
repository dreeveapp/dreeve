<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\VelocityDistributionChart;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VelocityDistributionChartTest extends TestCase
{
    /**
     * The velocity data is bucketed in the sport type's display unit at import time, so for a sail
     * activity the buckets are knots and the average marker has to be plotted in knots too.
     */
    #[DataProvider(methodName: 'provideUnitSystems')]
    public function testItMarksTheAverageInKnotsForSailActivities(UnitSystem $unitSystem): void
    {
        $chart = VelocityDistributionChart::create(
            velocityData: [8 => 60, 9 => 120, 10 => 200, 11 => 150, 12 => 90],
            averageSpeed: KmPerHour::from(18.52),
            sportType: SportType::SAIL,
            unitSystem: $unitSystem,
        )->build();

        $this->assertNotNull($chart);
        $this->assertEquals(10.0, $chart['series'][0]['markPoint']['data'][0]['value']);
    }

    public function testItMarksTheAverageInKmPerHourForRideActivities(): void
    {
        $chart = VelocityDistributionChart::create(
            velocityData: [28 => 60, 29 => 120, 30 => 200, 31 => 150, 32 => 90],
            averageSpeed: KmPerHour::from(30),
            sportType: SportType::RIDE,
            unitSystem: UnitSystem::METRIC,
        )->build();

        $this->assertNotNull($chart);
        $this->assertEquals(30.0, $chart['series'][0]['markPoint']['data'][0]['value']);
    }

    public static function provideUnitSystems(): array
    {
        return [
            [UnitSystem::METRIC],
            [UnitSystem::IMPERIAL],
        ];
    }
}
