<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\CadenceDistributionChart;
use PHPUnit\Framework\TestCase;

class CadenceDistributionChartTest extends TestCase
{
    public function testItDoublesCadenceForRunActivities(): void
    {
        $cadenceData = [80 => 10, 81 => 20, 82 => 15, 83 => 10, 84 => 5];

        $chart = CadenceDistributionChart::create(
            cadenceData: $cadenceData,
            averageCadence: 82,
            activityType: ActivityType::RUN,
        )->build();

        $this->assertNotNull($chart);
        $this->assertEquals(164, $chart['series'][0]['markPoint']['data'][0]['value']);
    }

    public function testItDoublesCadenceForWalkActivities(): void
    {
        $cadenceData = [55 => 10, 56 => 20, 57 => 15, 58 => 10, 59 => 5];

        $chart = CadenceDistributionChart::create(
            cadenceData: $cadenceData,
            averageCadence: 57,
            activityType: ActivityType::WALK,
        )->build();

        $this->assertNotNull($chart);
        $this->assertEquals(114, $chart['series'][0]['markPoint']['data'][0]['value']);
    }

    public function testItDoesNotDoubleCadenceForRideActivities(): void
    {
        $cadenceData = [80 => 10, 81 => 20, 82 => 15, 83 => 10, 84 => 5];

        $chart = CadenceDistributionChart::create(
            cadenceData: $cadenceData,
            averageCadence: 82,
            activityType: ActivityType::RIDE,
        )->build();

        $this->assertNotNull($chart);
        $this->assertEquals(82, $chart['series'][0]['markPoint']['data'][0]['value']);
    }
}
