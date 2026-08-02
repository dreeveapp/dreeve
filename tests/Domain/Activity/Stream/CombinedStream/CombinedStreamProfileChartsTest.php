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

    public function testItShouldThrowWhenYAxisDataIsEmpty(): void
    {
        $this->expectExceptionObject(new \RuntimeException('yAxisData data cannot be empty'));

        $this->buildChart(items: [
            ['yAxisData' => [], 'yAxisStreamType' => CombinedStreamType::WATTS],
        ]);
    }

    public function testItShouldAddHeartRateZoneMarkLines(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            [
                [
                    'name' => 'Z2',
                    'yAxis' => 112,
                    'lineStyle' => ['color' => '#D63522', 'type' => 'dashed', 'width' => 1, 'opacity' => 0.8],
                    'label' => ['backgroundColor' => '#D63522'],
                ],
                [
                    'name' => 'Z3',
                    'yAxis' => 131,
                    'lineStyle' => ['color' => '#BD2D22', 'type' => 'dashed', 'width' => 1, 'opacity' => 0.8],
                    'label' => ['backgroundColor' => '#BD2D22'],
                ],
                [
                    'name' => 'Z4',
                    'yAxis' => 149,
                    'lineStyle' => ['color' => '#942319', 'type' => 'dashed', 'width' => 1, 'opacity' => 0.8],
                    'label' => ['backgroundColor' => '#942319'],
                ],
                [
                    'name' => 'Z5',
                    'yAxis' => 168,
                    'lineStyle' => ['color' => '#6A1009', 'type' => 'dashed', 'width' => 1, 'opacity' => 0.8],
                    'label' => ['backgroundColor' => '#6A1009'],
                ],
            ],
            $chart['series'][0]['markLine']['data']
        );
    }

    public function testItShouldSkipHeartRateZoneMarkLinesOutsideOfTheYAxisRange(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [150, 160, 175], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertEquals(
            ['Z5'],
            array_column($chart['series'][0]['markLine']['data'], 'name')
        );
    }

    public function testItShouldNotAddHeartRateZoneMarkLinesWhenNoneAreInRange(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [40, 45, 50], 'yAxisStreamType' => CombinedStreamType::HEART_RATE],
        ]);

        $this->assertArrayNotHasKey('markLine', $chart['series'][0]);
    }

    public function testItShouldNotAddHeartRateZoneMarkLinesToOtherStreamTypes(): void
    {
        $chart = $this->buildChart(items: [
            ['yAxisData' => [100, 120, 140, 160, 175], 'yAxisStreamType' => CombinedStreamType::WATTS],
        ]);

        $this->assertArrayNotHasKey('markLine', $chart['series'][0]);
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
