<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\Activities;
use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Escape;
use App\Infrastructure\Theme\Theme;

final readonly class DistancePerMonthPerGearChart
{
    private function __construct(
        private Gears $gears,
        private Activities $activities,
        private UnitSystem $unitSystem,
        private Months $months,
    ) {
    }

    public static function create(
        Gears $gearCollection,
        Activities $activityCollection,
        UnitSystem $unitSystem,
        Months $months,
    ): self {
        return new self(
            gears: $gearCollection,
            activities: $activityCollection,
            unitSystem: $unitSystem,
            months: $months
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $gears = $this->gears->sortByIsRetired();

        $xAxisValues = [];
        $distancePerGearAndMonth = [];
        foreach ($this->months as $month) {
            $xAxisValues[] = $month->getLabel();
            foreach ($gears as $gear) {
                $distancePerGearAndMonth[(string) $gear->getId()][$month->getId()] = 0;
            }
        }

        foreach ($this->activities as $activity) {
            if (!$activity->getGearId() instanceof GearId) {
                continue;
            }
            if (!array_key_exists((string) $activity->getGearId(), $distancePerGearAndMonth)) {
                continue;
            }
            $month = $activity->getStartDate()->format(Month::MONTH_ID_FORMAT);
            $distancePerGearAndMonth[(string) $activity->getGearId()][$month] += $activity->getDistance()->toUnitSystem($this->unitSystem)->toFloat();
        }

        foreach ($distancePerGearAndMonth as $gearId => $months) {
            $distancePerGearAndMonth[$gearId] = array_map(round(...), $months);
        }

        $series = [];
        $selectedSeries = [];

        $unitSymbol = $this->unitSystem->distanceSymbol();

        foreach ($gears as $gear) {
            $gearName = Escape::forJsonEncode($gear->getName());
            $distanceInLastThreeMonths = array_sum(array_slice($distancePerGearAndMonth[(string) $gear->getId()], -3, 3));
            $selectedSeries[$gearName] = $distanceInLastThreeMonths > 0;

            $series[] = [
                'name' => $gearName,
                'type' => 'bar',
                'barGap' => 0,
                'itemStyle' => [
                    'color' => Theme::getColorForGear($gear->getId()),
                ],
                'emphasis' => [
                    'focus' => 'series',
                ],
                'label' => [
                    'show' => true,
                    'position' => 'insideBottom',
                    'verticalAlign' => 'middle',
                    'align' => 'left',
                    'color' => '#000',
                    'rotate' => 90,
                    'distance' => 15,
                    'formatter' => sprintf('{distance|{c} %s} - {a}', $unitSymbol),
                    'rich' => [
                        'distance' => [
                            'fontSize' => 14,
                            'fontWeight' => 'bold',
                        ],
                    ],
                ],
                'data' => array_values($distancePerGearAndMonth[(string) $gear->getId()]),
            ];
        }

        arsort($selectedSeries, SORT_NUMERIC);

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
                    'type' => 'none',
                ],
            ],
            'legend' => [
                'selected' => $selectedSeries,
                'data' => array_map(
                    fn (string $gearName): array => [
                        'name' => $gearName,
                    ],
                    array_keys($selectedSeries),
                ),
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
                    'startValue' => count($xAxisValues) - 4,
                    'endValue' => count($xAxisValues),
                    'minValueSpan' => 4,
                    'maxValueSpan' => 4,
                    'brushSelect' => false,
                    'zoomLock' => true,
                ],
            ],
            'series' => $series,
        ];
    }
}
