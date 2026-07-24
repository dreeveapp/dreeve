<?php

namespace App\Domain\Dashboard\Widget\WeeklyStats;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityType;
use App\Domain\Calendar\Week;
use App\Domain\Calendar\Weeks;
use App\Domain\Dashboard\StatsContext;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WeeklyStatsChart
{
    private function __construct(
        private Activities $activities,
        private UnitSystem $unitSystem,
        private ActivityType $activityType,
        /** @var StatsContext[] */
        private array $metricsDisplayOrder,
        private SerializableDateTime $now,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param StatsContext[] $metricsDisplayOrder
     */
    public static function create(
        Activities $activities,
        UnitSystem $unitSystem,
        ActivityType $activityType,
        array $metricsDisplayOrder,
        SerializableDateTime $now,
        TranslatorInterface $translator,
    ): self {
        return new self(
            activities: $activities,
            unitSystem: $unitSystem,
            activityType: $activityType,
            metricsDisplayOrder: $metricsDisplayOrder,
            now: $now,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $weeks = Weeks::create(
            startDate: $this->activities->getFirstActivityStartDate(),
            now: $this->now
        );
        $minZoomValueSpan = 10;
        $maxZoomValueSpan = 26;
        $data = $this->getData($weeks);

        if ([] === array_filter($data[StatsContext::DISTANCE->value])
            && [] === array_filter($data[StatsContext::MOVING_TIME->value])
            && [] === array_filter($data[StatsContext::ELEVATION->value])) {
            return []; // @codeCoverageIgnore
        }

        $xAxisLabels = [];
        /** @var Week $week */
        foreach ($weeks as $week) {
            if ($week == $weeks->getFirst() || in_array($week->getLabel(), $xAxisLabels)) {
                $xAxisLabels[] = '';
                continue;
            }
            $xAxisLabels[] = $week->getLabel();
        }

        $series = [];
        $serie = [
            'type' => 'line',
            'smooth' => false,
            'label' => [
                'show' => true,
                'rotate' => -45,
            ],
            'lineStyle' => [
                'width' => 1,
            ],
            'symbolSize' => 6,
            'showSymbol' => true,
            'areaStyle' => [
                'opacity' => 0.3,
                'color' => 'rgba(227, 73, 2, 0.3)',
            ],
            'emphasis' => [
                'focus' => 'series',
            ],
        ];

        $yAxis = [];

        foreach ($this->metricsDisplayOrder as $context) {
            if ([] === array_filter($data[$context->value])) {
                continue; // @codeCoverageIgnore
            }

            $unitSymbol = match ($context) {
                StatsContext::DISTANCE => $this->unitSystem->distanceSymbol(),
                StatsContext::MOVING_TIME => 'h',
                StatsContext::ELEVATION => $this->unitSystem->elevationSymbol(),
            };

            $series[] = array_merge_recursive(
                $serie,
                [
                    'name' => $context->trans($this->translator),
                    'data' => $data[$context->value],
                    'yAxisId' => $context->value,
                    'label' => [
                        'formatter' => '{@[1]}',
                    ],
                ],
            );

            $yAxis[] = [
                'id' => $context->value,
                'type' => 'value',
                'splitLine' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'formatter' => '{value} '.$unitSymbol,
                ],
                'position' => 'left',
            ];
        }

        return [
            'animation' => true,
            'backgroundColor' => null,
            'color' => [
                '#E34902',
            ],
            'grid' => [
                'left' => '10px',
                'right' => '20px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'legend' => [
                'show' => true,
                'selectedMode' => 'single',
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => count($weeks),
                    'endValue' => count($weeks) - $minZoomValueSpan,
                    'minValueSpan' => $minZoomValueSpan,
                    'maxValueSpan' => $maxZoomValueSpan,
                    'brushSelect' => false,
                    'zoomLock' => false,
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'boundaryGap' => false,
                    'axisTick' => [
                        'show' => false,
                    ],
                    'axisLabel' => [
                        'interval' => 0,
                    ],
                    'data' => $xAxisLabels,
                    'splitLine' => [
                        'show' => true,
                        'lineStyle' => [
                            'color' => '#E0E6F1',
                        ],
                    ],
                ],
            ],
            'yAxis' => $yAxis,
            'series' => $series,
        ];
    }

    /**
     * @return array{distance: float[], movingTime: float[], elevation: int[]}
     */
    private function getData(Weeks $weeks): array
    {
        $distancePerWeek = [];
        $timePerWeek = [];
        $elevationPerWeek = [];

        /** @var Week $week */
        foreach ($weeks as $week) {
            $distancePerWeek[$week->getId()] = 0;
            $timePerWeek[$week->getId()] = 0;
            $elevationPerWeek[$week->getId()] = 0;
        }

        /** @var Activity $activity */
        foreach ($this->activities as $activity) {
            $week = Week::fromDate($activity->getStartDate())->getId();
            if (!array_key_exists($week, $distancePerWeek)) {
                continue; // @codeCoverageIgnore
            }

            $distance = $activity->getDistance()->toUnitSystem($this->unitSystem);
            $elevation = $activity->getElevation()->toUnitSystem($this->unitSystem);
            $distancePerWeek[$week] += $distance->toFloat();
            $elevationPerWeek[$week] += $elevation->toFloat();
            $timePerWeek[$week] += $activity->getMovingTimeInSeconds();
        }

        $distancePerWeek = array_map(
            fn (float|int $distance): float => round($distance, $distance < 100 ? $this->activityType->getDistancePrecision() : 0),
            $distancePerWeek
        );
        $elevationPerWeek = array_map(
            fn (float|int $distance): int => (int) round($distance),
            $elevationPerWeek
        );
        $timePerWeek = array_map(fn (int $time): float => round($time / 3600, 1), $timePerWeek);

        return [
            StatsContext::DISTANCE->value => array_values($distancePerWeek),
            StatsContext::MOVING_TIME->value => array_values($timePerWeek),
            StatsContext::ELEVATION->value => array_values($elevationPerWeek),
        ];
    }
}
