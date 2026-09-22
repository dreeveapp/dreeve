<?php

namespace App\Domain\Dashboard\Widget\RollingStats;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\StatsContext;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RollingStatsChart
{
    private const int MIN_ZOOM_VALUE_SPAN = 28;
    private const int MAX_ZOOM_VALUE_SPAN = 366;

    private function __construct(
        private Activities $activities,
        private UnitSystem $unitSystem,
        private ActivityType $activityType,
        /** @var StatsContext[] */
        private array $metricsDisplayOrder,
        private RollingWindows $rollingWindows,
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
        RollingWindows $rollingWindows,
        TranslatorInterface $translator,
    ): self {
        return new self(
            activities: $activities,
            unitSystem: $unitSystem,
            activityType: $activityType,
            metricsDisplayOrder: $metricsDisplayOrder,
            rollingWindows: $rollingWindows,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = $this->getData();

        if ([] === array_filter($data[StatsContext::DISTANCE->value])
            && [] === array_filter($data[StatsContext::MOVING_TIME->value])
            && [] === array_filter($data[StatsContext::ELEVATION->value])) {
            return []; // @codeCoverageIgnore
        }

        $xAxisLabels = [];
        $previousLabel = null;
        /** @var RollingWindow $rollingWindow */
        foreach ($this->rollingWindows as $rollingWindow) {
            $label = $rollingWindow->getLabel();
            $xAxisLabels[] = $rollingWindow == $this->rollingWindows->getFirst() || $label === $previousLabel ? '' : $label;
            $previousLabel = $label;
        }

        $series = [];
        $serie = [
            'type' => 'line',
            'smooth' => false,
            'lineStyle' => [
                'width' => 1,
            ],
            'symbolSize' => 6,
            'showSymbol' => false,
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
                    'tooltip' => [
                        'valueFormatter' => match ($context) {
                            StatsContext::DISTANCE => 'callback:formatDistance',
                            StatsContext::MOVING_TIME => 'callback:formatHours',
                            StatsContext::ELEVATION => 'callback:formatElevation',
                        },
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
            'tooltip' => [
                'trigger' => 'axis',
            ],
            'legend' => [
                'show' => true,
                'selectedMode' => 'single',
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => count($this->rollingWindows),
                    'endValue' => max(0, count($this->rollingWindows) - self::MIN_ZOOM_VALUE_SPAN),
                    'minValueSpan' => self::MIN_ZOOM_VALUE_SPAN,
                    'maxValueSpan' => self::MAX_ZOOM_VALUE_SPAN,
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
                        'interval' => 'auto',
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
    private function getData(): array
    {
        $distancePerDay = $timePerDay = $elevationPerDay = [];

        $firstRollingWindow = $this->rollingWindows->getFirst();
        $lastRollingWindow = $this->rollingWindows->getLast();
        if (!$firstRollingWindow instanceof RollingWindow || !$lastRollingWindow instanceof RollingWindow) {
            // @codeCoverageIgnoreStart
            return [
                StatsContext::DISTANCE->value => [],
                StatsContext::MOVING_TIME->value => [],
                StatsContext::ELEVATION->value => [],
            ];
            // @codeCoverageIgnoreEnd
        }

        $day = $firstRollingWindow->getFrom();
        $lastDay = $lastRollingWindow->getTo();
        while ($day->isBeforeOrOn($lastDay)) {
            $distancePerDay[$day->format('Y-m-d')] = 0.0;
            $timePerDay[$day->format('Y-m-d')] = 0.0;
            $elevationPerDay[$day->format('Y-m-d')] = 0.0;

            $day = SerializableDateTime::fromString($day->modify('+1 day')->format('Y-m-d'));
        }

        /** @var Activity $activity */
        foreach ($this->activities as $activity) {
            $startDate = $activity->getStartDate()->format('Y-m-d');
            if (!array_key_exists($startDate, $distancePerDay)) {
                continue; // @codeCoverageIgnore
            }

            $distancePerDay[$startDate] += $activity->getDistance()->toUnitSystem($this->unitSystem)->toFloat();
            $elevationPerDay[$startDate] += $activity->getElevation()->toUnitSystem($this->unitSystem)->toFloat();
            $timePerDay[$startDate] += $activity->getMovingTimeInSeconds();
        }

        return [
            StatsContext::DISTANCE->value => array_map(
                fn (float $distance): float => round($distance, $distance < 100 ? $this->activityType->getDistancePrecision() : 0),
                $this->sumPerRollingWindow($distancePerDay)
            ),
            StatsContext::MOVING_TIME->value => array_map(
                fn (float $time): float => round($time / 3600, 1),
                $this->sumPerRollingWindow($timePerDay)
            ),
            StatsContext::ELEVATION->value => array_map(
                fn (float $elevation): int => (int) round($elevation),
                $this->sumPerRollingWindow($elevationPerDay)
            ),
        ];
    }

    /**
     * @param array<string, float> $totalsPerDay
     *
     * @return float[]
     */
    private function sumPerRollingWindow(array $totalsPerDay): array
    {
        $days = array_keys($totalsPerDay);
        $indexPerDay = array_flip($days);

        $runningTotal = 0.0;
        $totalUpToAndIncluding = [];
        foreach ($days as $index => $day) {
            $runningTotal += $totalsPerDay[$day];
            $totalUpToAndIncluding[$index] = $runningTotal;
        }

        $totals = [];
        /** @var RollingWindow $rollingWindow */
        foreach ($this->rollingWindows as $rollingWindow) {
            $lastIndex = $indexPerDay[$rollingWindow->getTo()->format('Y-m-d')];
            $firstIndex = $indexPerDay[$rollingWindow->getFrom()->format('Y-m-d')];

            $totals[] = $totalUpToAndIncluding[$lastIndex] - ($firstIndex > 0 ? $totalUpToAndIncluding[$firstIndex - 1] : 0.0);
        }

        return $totals;
    }
}
