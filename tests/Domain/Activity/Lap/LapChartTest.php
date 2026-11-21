<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Lap;

use App\Domain\Activity\Lap\ActivityLapId;
use App\Domain\Activity\Lap\ActivityLapPower;
use App\Domain\Activity\Lap\ActivityLapPowers;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Domain\Activity\Lap\LapChart;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class LapChartTest extends ContainerTestCase
{
    public function testItReturnsNullForEmptyLaps(): void
    {
        $this->assertNull($this->buildChart(ActivityLaps::empty()));
    }

    public function testItBuildsASpeedChartWithHeartRate(): void
    {
        $chart = $this->buildChart(ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withLapNumber(1)
                ->withDistance(Meter::from(1000))
                ->withAverageSpeed(MetersPerSecond::from(10))
                ->withAverageHeartRate(150)
                ->build(),
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-2'))
                ->withLapNumber(2)
                ->withDistance(Meter::from(1200))
                ->withAverageSpeed(MetersPerSecond::from(12))
                ->withAverageHeartRate(160)
                ->build(),
        ]));

        $this->assertNotNull($chart);
        $this->assertSame(['1', '2'], $chart['xAxis']['data']);
        $this->assertSame('bar', $chart['series'][0]['type']);
        $this->assertEqualsWithDelta(36.0, $chart['series'][0]['data'][0]['value'], 0.001);
        $this->assertSame('line', $chart['series'][1]['type']);
        $this->assertSame(150, $chart['series'][1]['data'][0]['value']);
        $this->assertTrue($chart['legend']['show']);
        $this->assertTrue($chart['yAxis'][1]['show']);
    }

    public function testItHidesTheHeartRateSeriesWhenThereIsNone(): void
    {
        $chart = $this->buildChart(ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withDistance(Meter::from(1000))
                ->withAverageSpeed(MetersPerSecond::from(10))
                ->build(),
        ]));

        $this->assertNotNull($chart);
        $this->assertCount(1, $chart['series']);
        $this->assertFalse($chart['legend']['show']);
        $this->assertFalse($chart['yAxis'][1]['show']);
    }

    public function testItAddsThePowerSeriesOnAThirdYAxis(): void
    {
        $laps = ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withLapNumber(1)
                ->withDistance(Meter::from(1000))
                ->withAverageSpeed(MetersPerSecond::from(10))
                ->withAverageHeartRate(150)
                ->build(),
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-2'))
                ->withLapNumber(2)
                ->withDistance(Meter::from(1200))
                ->withAverageSpeed(MetersPerSecond::from(12))
                ->withAverageHeartRate(160)
                ->build(),
        ]);
        $lapPowers = ActivityLapPowers::fromArray([
            ActivityLapPower::create(ActivityLapId::fromUnprefixed('lap-1'), 200, 400),
            ActivityLapPower::create(ActivityLapId::fromUnprefixed('lap-2'), 250, 500),
        ]);

        $chart = $this->buildChart($laps, $lapPowers);

        $this->assertNotNull($chart);
        $this->assertCount(3, $chart['series']);
        $this->assertSame('line', $chart['series'][2]['type']);
        $this->assertSame(2, $chart['series'][2]['yAxisIndex']);
        $this->assertSame(200, $chart['series'][2]['data'][0]['value']);
        $this->assertSame(250, $chart['series'][2]['data'][1]['value']);
        $this->assertTrue($chart['yAxis'][2]['show']);
        $this->assertTrue($chart['legend']['show']);
        $this->assertContains($chart['series'][2]['name'], $chart['legend']['data']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildChart(ActivityLaps $laps, ?ActivityLapPowers $lapPowers = null): ?array
    {
        return LapChart::create(
            laps: $laps,
            lapPowers: $lapPowers ?? ActivityLapPowers::empty(),
            sportType: SportType::RIDE,
            unitSystem: UnitSystem::METRIC,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build();
    }
}
