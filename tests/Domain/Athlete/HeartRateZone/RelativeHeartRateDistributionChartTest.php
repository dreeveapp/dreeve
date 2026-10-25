<?php

namespace App\Tests\Domain\Athlete\HeartRateZone;

use App\Domain\Athlete\HeartRateZone\HeartRateZoneMode;
use App\Domain\Athlete\HeartRateZone\HeartRateZones;
use App\Domain\Athlete\HeartRateZone\RelativeHeartRateDistribution;
use App\Domain\Athlete\HeartRateZone\RelativeHeartRateDistributionChart;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class RelativeHeartRateDistributionChartTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testBuild(): void
    {
        $chart = RelativeHeartRateDistributionChart::create(
            relativeHeartRateDistribution: RelativeHeartRateDistribution::fromSecondsPerPercentage([
                45 => 10.0,
                50 => 30.0,
                60 => 100.0,
                61 => 200.0,
                70 => 400.0,
                71 => 150.0,
                85 => 80.0,
                94 => 30.0,
            ]),
            heartRateZones: HeartRateZones::fromScalarValues(
                mode: HeartRateZoneMode::RELATIVE,
                zones: [
                    'zone1' => ['from' => 50, 'to' => 60],
                    'zone2' => ['from' => 61, 'to' => 70],
                    'zone3' => ['from' => 71, 'to' => 80],
                    'zone4' => ['from' => 81, 'to' => 90],
                    'zone5' => ['from' => 91, 'to' => null],
                ],
            ),
            athleteMaxHeartRate: 190,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build();

        $this->assertEquals([['value' => 10.0, 'name' => '114 bpm'], '-'], [$chart['series'][0]['data'][15], $chart['series'][1]['data'][15]]);
        $this->assertEquals(['-', ['value' => 20.0, 'name' => '115–116 bpm']], [$chart['series'][0]['data'][16], $chart['series'][1]['data'][16]]);
        $this->assertEquals(['value' => 1.0, 'name' => '≤86 bpm'], $chart['series'][5]['data'][0]);
        $this->assertEquals(['value' => 0.0, 'name' => '≥180 bpm'], $chart['series'][4]['data'][50]);
        $this->assertEquals(['Zone 1 (13%)', 'Zone 2 (60%)', 'Zone 3 (15%)', 'Zone 4 (8%)', 'Zone 5 (3%)'], $chart['legend']['data']);
        $this->assertMatchesJsonSnapshot(Json::encode($chart));
    }
}
