<?php

namespace App\Tests\Domain\Segment\AddCustomSegment;

use App\Domain\Segment\AddCustomSegment\AddSegmentElevationChart;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class AddSegmentElevationChartTest extends TestCase
{
    use MatchesSnapshots;

    public function testBuild(): void
    {
        $chart = AddSegmentElevationChart::create(
            distances: [0, 450.4, 1200, 2500.75],
            altitudes: [980.4, 1010, 1100.6, 1050],
            unitSystem: UnitSystem::METRIC,
        )->build();

        $this->assertSame(['0.00', '0.45', '1.20', '2.50'], $chart['xAxis']['data']);
        $this->assertSame([980, 1010, 1101, 1050], $chart['series'][0]['data']);
        $this->assertSame([967, 1114], [$chart['yAxis']['min'], $chart['yAxis']['max']]);
        $this->assertSame('75px', $chart['grid']['left']);
        $this->assertSame('{b0} km · {c0} m', $chart['tooltip']['formatter']);
        $this->assertMatchesJsonSnapshot(Json::encode($chart));
    }

    public function testItConvertsToImperial(): void
    {
        $chart = AddSegmentElevationChart::create(
            distances: [0, 1609.344],
            altitudes: [100, 200],
            unitSystem: UnitSystem::IMPERIAL,
        )->build();

        $this->assertSame(['0.00', '1.00'], $chart['xAxis']['data']);
        $this->assertSame([328, 656], $chart['series'][0]['data']);
        $this->assertSame('65px', $chart['grid']['left']);
        $this->assertSame('{b0} mi · {c0} ft', $chart['tooltip']['formatter']);
        $this->assertSame('mi', $chart['xAxis']['axisLabel']['formatter']['args']['suffix']);
        $this->assertSame('{value} ft', $chart['yAxis']['axisLabel']['formatter']);
    }
}
