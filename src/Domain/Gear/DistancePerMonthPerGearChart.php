<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonthResponse;
use App\Domain\Theme\Theme;
use App\Infrastructure\ECharts\EChartsCallback;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Escape;

final readonly class DistancePerMonthPerGearChart
{
    private function __construct(
        private Gears $gears,
        private FindGearUsagePerMonthResponse $gearUsage,
        private UnitSystem $unitSystem,
        private Months $months,
        private Theme $theme,
    ) {
    }

    public static function create(
        Gears $gearCollection,
        FindGearUsagePerMonthResponse $gearUsage,
        UnitSystem $unitSystem,
        Months $months,
        Theme $theme,
    ): self {
        return new self(
            gears: $gearCollection,
            gearUsage: $gearUsage,
            unitSystem: $unitSystem,
            months: $months,
            theme: $theme,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $gears = $this->gears->sortByIsRetired();
        $xAxisValues = $this->months->map(fn (Month $month): string => $month->getLabel());

        $series = [];
        foreach ($gears as $gear) {
            $usage = $this->gearUsage->getFor($gear->getId());

            $series[] = [
                'name' => Escape::forJsonEncode($gear->getName()),
                'type' => 'bar',
                'stack' => 'distance',
                'barMaxWidth' => 28,
                'itemStyle' => [
                    'color' => $this->theme->getColorForGear($gear->getId()),
                ],
                'emphasis' => [
                    'focus' => 'series',
                ],
                'data' => $this->months->map(
                    fn (Month $month): float => round($usage->getDistanceFor($month)->toUnitSystem($this->unitSystem)->toFloat())
                ),
            ];
        }

        return [
            'animation' => true,
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
                ],
                'formatter' => EChartsCallback::formatStackedDistanceTooltip($this->unitSystem->distanceSymbol()),
            ],
            'legend' => [
                'type' => 'scroll',
                'pageButtonItemGap' => 2,
                'pageIconSize' => 10,
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'axisTick' => [
                        'show' => false,
                    ],
                    'data' => $xAxisValues,
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => max(0, count($xAxisValues) - 24),
                    'endValue' => count($xAxisValues) - 1,
                    'brushSelect' => false,
                ],
            ],
            'series' => $series,
        ];
    }
}
