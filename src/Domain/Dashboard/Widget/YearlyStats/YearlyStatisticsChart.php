<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\YearlyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStatsPerDay\FindYearlyStatsPerDayResponse;
use App\Infrastructure\Theme\Theme;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class YearlyStatisticsChart
{
    private function __construct(
        private FindYearlyStatsPerDayResponse $yearStats,
        private Years $uniqueYears,
        private ActivityType $activityType,
        private StatsContext $context,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
        private SerializableDateTime $now,
        private int $enableLastXYearsByDefault,
    ) {
    }

    public static function create(
        FindYearlyStatsPerDayResponse $yearStats,
        Years $uniqueYears,
        ActivityType $activityType,
        StatsContext $context,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
        SerializableDateTime $now,
        int $enableLastXYearsByDefault,
    ): self {
        return new self(
            yearStats: $yearStats,
            uniqueYears: $uniqueYears,
            activityType: $activityType,
            context: $context,
            unitSystem: $unitSystem,
            translator: $translator,
            now: $now,
            enableLastXYearsByDefault: $enableLastXYearsByDefault,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $months = [
            1 => $this->translator->trans('Jan'),
            2 => $this->translator->trans('Feb'),
            3 => $this->translator->trans('Mar'),
            4 => $this->translator->trans('Apr'),
            5 => $this->translator->trans('May'),
            6 => $this->translator->trans('Jun'),
            7 => $this->translator->trans('Jul'),
            8 => $this->translator->trans('Aug'),
            9 => $this->translator->trans('Sep'),
            10 => $this->translator->trans('Oct'),
            11 => $this->translator->trans('Nov'),
            12 => $this->translator->trans('Dec'),
        ];

        $xAxisLabels = [];
        foreach ($months as $month) {
            $xAxisLabels = [...$xAxisLabels, ...array_fill(0, 31, $month)];
        }

        $series = [];
        $selectedSeries = [];
        $delta = 1;
        /** @var \App\Infrastructure\ValueObject\Time\Year $year */
        foreach ($this->uniqueYears as $year) {
            $selectedSeries[$year->toInt()] = $delta <= $this->enableLastXYearsByDefault;
            ++$delta;

            $data = [];
            $previousValue = match ($this->context) {
                StatsContext::MOVING_TIME => Hour::zero(),
                StatsContext::DISTANCE => Kilometer::zero()->toUnitSystem($this->unitSystem),
                StatsContext::ELEVATION => Meter::zero()->toUnitSystem($this->unitSystem),
            };
            foreach (array_keys($months) as $month) {
                for ($dayOfMonth = 1; $dayOfMonth <= 31; ++$dayOfMonth) {
                    if (!checkdate($month, $dayOfMonth, $year->toInt())) {
                        $data[] = round($previousValue->toFloat());
                        continue;
                    }

                    $date = SerializableDateTime::fromString(sprintf(
                        '%04d-%02d-%02d',
                        $year->toInt(),
                        $month,
                        $dayOfMonth),
                    );

                    if ($date->isAfter($this->now)) {
                        break 2;
                    }

                    $value = match ($this->context) {
                        StatsContext::MOVING_TIME => $this->yearStats->getMovingTimeFor($date, $this->activityType)?->toHour(),
                        StatsContext::DISTANCE => $this->yearStats->getDistanceFor($date, $this->activityType)?->toUnitSystem($this->unitSystem),
                        StatsContext::ELEVATION => $this->yearStats->getElevationFor($date, $this->activityType)?->toUnitSystem($this->unitSystem),
                    };

                    if (!$value) {
                        $value = $previousValue;
                    }
                    $previousValue = $value;
                    $data[] = round($value->toFloat());
                }
            }

            $series[(string) $year] = [
                'name' => (string) $year,
                'type' => 'line',
                'smooth' => true,
                'showSymbol' => false,
                'itemStyle' => [
                    'color' => Theme::getColorForYear($year),
                ],
                'data' => $data,
            ];
        }

        return [
            'animation' => true,
            'backgroundColor' => null,
            'grid' => [
                'left' => '2px',
                'right' => '10px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'axisTick' => [
                        'show' => false,
                    ],
                    'axisLabel' => [
                        'interval' => 31,
                    ],
                    'data' => $xAxisLabels,
                ],
            ],
            'legend' => [
                'selected' => $selectedSeries,
                'show' => true,
            ],
            'tooltip' => [
                'show' => true,
                'trigger' => 'axis',
                'valueFormatter' => match ($this->context) {
                    StatsContext::MOVING_TIME => 'callback:formatHours',
                    StatsContext::DISTANCE => 'callback:formatDistance',
                    StatsContext::ELEVATION => 'callback:formatElevation',
                },
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'nameRotate' => 90,
                    'nameLocation' => 'middle',
                    'nameGap' => 50,
                    'axisLabel' => [
                        'formatter' => match ($this->context) {
                            StatsContext::MOVING_TIME => '{value}h',
                            StatsContext::DISTANCE => '{value}'.$this->unitSystem->distanceSymbol(),
                            StatsContext::ELEVATION => '{value}'.$this->unitSystem->elevationSymbol(),
                        },
                    ],
                ],
            ],
            'toolbox' => [
                'show' => true,
                'feature' => [
                    'dataZoom' => [
                        'show' => true,
                        'yAxisIndex' => 'none',
                    ],
                    'restore' => [
                        'show' => true,
                    ],
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'start' => 0,
                    'end' => 100,
                    'brushSelect' => true,
                    'zoomLock' => false,
                    'zoomOnMouseWheel' => false,
                ],
            ],
            'series' => array_values($series),
        ];
    }
}
