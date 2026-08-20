<?php

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneMode;
use App\Domain\Athlete\HeartRateZone\HeartRateZones;
use App\Domain\Theme\Theme;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Contracts\Translation\TranslatorInterface;

class CombinedStreamProfileChartsTest extends ContainerTestCase
{
    #[TestWith(data: [1, '65px'])]
    #[TestWith(data: [4, '75px'])]
    #[TestWith(data: [5, '85px'])]
    public function testGridLeftPadding(int $maxYAxisDigits, string $expectedPadding): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            maximumNumberOfDigitsOnYAxis: $maxYAxisDigits,
        );

        $this->assertEquals(
            $expectedPadding,
            $chart['grid'][0]['left']
        );
    }

    #[TestWith(data: [1, false, 245])]
    #[TestWith(data: [4, false, 620])]
    #[TestWith(data: [1, true, 268])]
    #[TestWith(data: [4, true, 643])]
    public function testTotalHeightFor(int $numberOfLanes, bool $hasTemperatureRibbon, int $expectedHeight): void
    {
        $this->assertEquals(
            $expectedHeight,
            CombinedStreamProfileCharts::totalHeightFor($numberOfLanes, $hasTemperatureRibbon)
        );
    }

    public function testItShouldThrowWhenYAxisDataIsEmpty(): void
    {
        $this->expectExceptionObject(new \RuntimeException('yAxisData data cannot be empty'));

        $this->buildChart(items: [
            ['yAxisData' => [], 'yAxisStreamType' => CombinedStreamType::WATTS],
        ]);
    }

    public function testItShouldAddABandPerHeartRateZone(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            [
                'data' => [
                    [
                        ['xAxis' => 'min', 'yAxis' => 183, 'itemStyle' => ['color' => '#3E444D'], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 100],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 112, 'itemStyle' => ['color' => '#DF584A', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 100],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 131, 'itemStyle' => ['color' => '#D63522', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 112],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 149, 'itemStyle' => ['color' => '#BD2D22', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 131],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 168, 'itemStyle' => ['color' => '#942319', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 149],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 183, 'itemStyle' => ['color' => '#6A1009', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 168],
                    ],
                ],
            ],
            $chart['series'][0]['markArea']
        );
    }

    public function testItShouldTagEveryHeartRateWithItsZoneForTheTooltip(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [80, 92, 120, 140, 155, 170], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            [
                80,
                ['value' => 92, 'extra' => 'zone 1'],
                ['value' => 120, 'extra' => 'zone 2'],
                ['value' => 140, 'extra' => 'zone 3'],
                ['value' => 155, 'extra' => 'zone 4'],
                ['value' => 170, 'extra' => 'zone 5'],
            ],
            $chart['series'][0]['data']
        );
    }

    public function testItShouldRenderTheHeartRateStreamAsALineWithoutArea(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(2, $chart['series'][0]['lineStyle']['width']);
        $this->assertArrayNotHasKey('areaStyle', $chart['series'][0]);
    }

    public function testItShouldSkipHeartRateZonesOutsideOfTheYAxisRange(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [150, 160, 175], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            [
                'data' => [
                    [
                        ['xAxis' => 'min', 'yAxis' => 178, 'itemStyle' => ['color' => '#3E444D'], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 150],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 168, 'itemStyle' => ['color' => '#942319', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 150],
                    ],
                    [
                        ['xAxis' => 'min', 'yAxis' => 178, 'itemStyle' => ['color' => '#6A1009', 'opacity' => 0.35], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 168],
                    ],
                ],
            ],
            $chart['series'][0]['markArea']
        );
    }

    public function testItShouldOnlyKeepTheBackgroundWhenNoZoneIsInRange(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [40, 45, 50], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            [
                'data' => [
                    [
                        ['xAxis' => 'min', 'yAxis' => 51, 'itemStyle' => ['color' => '#3E444D'], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 40],
                    ],
                ],
            ],
            $chart['series'][0]['markArea']
        );
    }

    public function testItShouldLeaveOtherStreamTypesUntouched(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::WATTS],
        ]);

        $this->assertEquals(
            [
                'data' => [
                    [
                        ['xAxis' => 'min', 'yAxis' => 183, 'itemStyle' => ['color' => '#3E444D'], 'emphasis' => ['disabled' => true]],
                        ['xAxis' => 'max', 'yAxis' => 100],
                    ],
                ],
            ],
            $chart['series'][0]['markArea']
        );
        $this->assertEquals(0, $chart['series'][0]['lineStyle']['width']);
        $this->assertEquals(
            ['opacity' => 1, 'origin' => 'start'],
            $chart['series'][0]['areaStyle']
        );
    }

    public function testItShouldNotRenderARibbonWithoutTemperatures(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::WATTS],
        ]);

        $this->assertCount(1, $chart['grid']);
        $this->assertArrayNotHasKey('visualMap', $chart);
        $this->assertEquals(Theme::POSITION_TOP, $chart['xAxis'][0]['position']);
    }

    public function testItShouldRenderTheTemperatureAsAShortRibbonAboveTheLanes(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 15, 18, 22, 21],
        );

        $this->assertCount(2, $chart['grid']);
        $this->assertEquals('18px', $chart['grid'][0]['height']);
        $this->assertEquals('90px', $chart['grid'][0]['top']);
        // The lane sits below the 18px ribbon plus the 5px gap.
        $this->assertEquals('120px', $chart['grid'][1]['height']);
        $this->assertEquals('113px', $chart['grid'][1]['top']);
    }

    public function testItShouldHandTheTopXAxisToTheRibbon(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
                ['yAxisData' => [10, 12, 14], 'yAxisStreamType' => CombinedStreamType::ALTITUDE],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertEquals(Theme::POSITION_TOP, $chart['xAxis'][0]['position']);
        $this->assertNull($chart['xAxis'][1]['position']);
        $this->assertEquals(Theme::POSITION_BOTTOM, $chart['xAxis'][2]['position']);
    }

    public function testItShouldPointEveryLaneAtItsOwnGridWhenARibbonIsPresent(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
                ['yAxisData' => [10, 12, 14], 'yAxisStreamType' => CombinedStreamType::ALTITUDE],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertCount(3, $chart['grid']);
        $this->assertEquals([0, 1, 2], array_column($chart['xAxis'], 'gridIndex'));
        $this->assertEquals([0, 1, 2], array_column($chart['yAxis'], 'gridIndex'));
        $this->assertEquals([0, 1, 2], array_column($chart['series'], 'xAxisIndex'));
        $this->assertEquals([0, 1, 2], array_column($chart['series'], 'yAxisIndex'));
        $this->assertEquals([0, 1, 2], $chart['dataZoom'][0]['xAxisIndex']);
    }

    public function testItShouldRenderTheRibbonAsASolidLineWithoutArea(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertArrayNotHasKey('visualMap', $chart);
        $this->assertArrayNotHasKey('areaStyle', $chart['series'][0]);
        $this->assertEquals('#fc8452', $chart['series'][0]['color']);
        $this->assertEquals(2, $chart['series'][0]['lineStyle']['width']);
    }

    public function testItShouldLeaveTheRibbonBackgroundTransparent(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertArrayNotHasKey('markArea', $chart['series'][0]);
        // The lanes below it keep theirs.
        $this->assertEquals('#3E444D', $chart['series'][1]['markArea']['data'][0][0]['itemStyle']['color']);
    }

    public function testItShouldNotDrawAnAxisRuleAboveTheRibbon(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertFalse($chart['xAxis'][0]['axisLine']['show']);
        $this->assertTrue($chart['xAxis'][0]['axisLabel']['show']);
    }

    public function testItShouldSmoothTheRibbonLine(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertTrue($chart['series'][0]['smooth']);
    }

    public function testItShouldKeepTheRibbonAxisVisibleWhenTheSensorNeverMoves(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [20, 20, 20],
        );

        $this->assertEquals(19, $chart['yAxis'][0]['min']);
        $this->assertEquals(21, $chart['yAxis'][0]['max']);
    }

    public function testItShouldLabelTheRibbonWithTheUnitSymbol(): void
    {
        $chart = $this->buildChart(
            items: [
                ['yAxisData' => [100, 120, 140], 'yAxisStreamType' => CombinedStreamType::WATTS],
            ],
            temperatures: [14, 18, 22],
        );

        $this->assertEquals('°C', $chart['yAxis'][0]['name']);
        $this->assertFalse($chart['yAxis'][0]['axisLabel']['show']);
        $this->assertEquals('°C', $chart['series'][0]['name']);
        $this->assertEquals([14, 18, 22], $chart['series'][0]['data']);
    }

    /**
     * @param list<array{yAxisData: array<int, int|float>, yAxisStreamType: CombinedStreamType}> $items
     * @param array<int, int|float>                                                              $temperatures
     *
     * @return array<string, mixed>
     */
    private function buildChart(
        array $items,
        int $maximumNumberOfDigitsOnYAxis = 3,
        array $temperatures = [],
    ): array {
        return CombinedStreamProfileCharts::create(
            items: $items,
            topXAxisData: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            bottomXAxisData: [],
            bottomXAxisSuffix: null,
            grades: [],
            temperatures: $temperatures,
            maximumNumberOfDigitsOnYAxis: $maximumNumberOfDigitsOnYAxis,
            unitSystem: UnitSystem::METRIC,
            sportType: SportType::RIDE,
            athleteMaxHeartRate: 185,
            heartRateZones: HeartRateZones::fromScalarValues(
                mode: HeartRateZoneMode::RELATIVE,
                zones: [
                    'zone1' => ['from' => 50, 'to' => 60],
                    'zone2' => ['from' => 61, 'to' => 70],
                    'zone3' => ['from' => 71, 'to' => 80],
                    'zone4' => ['from' => 81, 'to' => 90],
                    'zone5' => ['from' => 91, 'to' => null],
                ]
            ),
            translator: $this->getContainer()->get(TranslatorInterface::class)
        )->build();
    }
}
