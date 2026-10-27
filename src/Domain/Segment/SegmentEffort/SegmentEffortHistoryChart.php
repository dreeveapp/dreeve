<?php

namespace App\Domain\Segment\SegmentEffort;

use App\Infrastructure\ECharts\EChartsCallback;

final readonly class SegmentEffortHistoryChart
{
    private function __construct(
        private SegmentEfforts $segmentEfforts,
    ) {
    }

    public static function create(
        SegmentEfforts $segmentEfforts,
    ): self {
        return new self($segmentEfforts);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = [];

        $minYAxisValue = 100000;
        foreach ($this->segmentEfforts as $segmentEffort) {
            $segmentEffortElapsedTimeInSeconds = $segmentEffort->getElapsedTimeInSeconds();
            $segmentEffortStartDate = $segmentEffort->getStartDateTime();
            $data[] = [$segmentEffortStartDate->format('Y-m-d'), $segmentEffortElapsedTimeInSeconds];
            $minYAxisValue = min($minYAxisValue, $segmentEffortElapsedTimeInSeconds);
        }

        return [
            'animation' => false,
            'grid' => [
                'top' => '30px',
                'left' => '10px',
                'right' => '10px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'show' => true,
                'trigger' => 'axis',
                'valueFormatter' => EChartsCallback::formatSecondsTrimZero(),
            ],
            'xAxis' => [
                [
                    'type' => 'time',
                    'boundaryGap' => false,
                    'axisTick' => [
                        'show' => false,
                    ],
                    'axisLabel' => [
                        'formatter' => [
                            'year' => '{yyyy}',
                            'month' => '{MMM}',
                            'day' => '{d}',
                            'hour' => '{HH}:{mm}',
                            'minute' => '{HH}:{mm}',
                            'second' => '{HH}:{mm}:{ss}',
                            'millisecond' => '{hh}:{mm}:{ss} {SSS}',
                            'none' => '{yyyy}-{MM}-{dd}',
                        ],
                    ],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'min' => max(0, floor($minYAxisValue / 5) * 5),
                    'axisLabel' => [
                        'formatter' => EChartsCallback::formatSecondsTrimZero(),
                    ],
                ],
            ],
            'series' => [
                [
                    'color' => [
                        '#E34902',
                    ],
                    'type' => 'scatter',
                    'data' => $data,
                ],
            ],
            'toolbox' => [
                'show' => true,
                'feature' => [
                    'restore' => [
                        'show' => true,
                    ],
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => $this->segmentEfforts->getFirst()?->getStartDateTime()->modify('-1 year')->format('Y-m-d'),
                    'end' => 100,
                    'brushSelect' => true,
                    'zoomLock' => false,
                    'zoomOnMouseWheel' => false,
                    'labelFormatter' => '',
                ],
            ],
        ];
    }
}
