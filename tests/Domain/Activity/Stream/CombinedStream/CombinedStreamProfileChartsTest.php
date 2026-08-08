<?php

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneMode;
use App\Domain\Athlete\HeartRateZone\HeartRateZones;
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

    #[TestWith(data: [1, 245])]
    #[TestWith(data: [4, 620])]
    public function testTotalHeightFor(int $numberOfLanes, int $expectedHeight): void
    {
        $this->assertEquals($expectedHeight, CombinedStreamProfileCharts::totalHeightFor($numberOfLanes));
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

    /**
     * @param list<array{yAxisData: array<int, int|float>, yAxisStreamType: CombinedStreamType}> $items
     *
     * @return array<string, mixed>
     */
    private function buildChart(array $items, int $maximumNumberOfDigitsOnYAxis = 3): array
    {
        return CombinedStreamProfileCharts::create(
            items: $items,
            topXAxisData: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            bottomXAxisData: [],
            bottomXAxisSuffix: null,
            grades: [],
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
