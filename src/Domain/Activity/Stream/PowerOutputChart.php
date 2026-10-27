<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Infrastructure\ECharts\EChartsCallback;

final readonly class PowerOutputChart
{
    private const array X_AXIS_TICKS_IN_SECONDS = [2, 5, 10, 30, 60, 120, 300, 600, 1200, 3600];

    /**
     * @param string[] $visibleSeries
     */
    private function __construct(
        private BestPowerOutputs $bestPowerOutputs,
        private array $visibleSeries,
    ) {
    }

    /**
     * @param string[] $visibleSeries
     */
    public static function create(
        BestPowerOutputs $bestPowerOutputs,
        array $visibleSeries,
    ): self {
        return new self($bestPowerOutputs, $visibleSeries);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $series = [];
        $selectedSeries = [];
        $minPowerOutput = PHP_INT_MAX;
        $maxPowerOutput = 100;

        foreach ($this->bestPowerOutputs as $bestPowerOutputs) {
            [$description, $powerOutputs] = $bestPowerOutputs;
            assert($powerOutputs instanceof PowerOutputs);

            $data = $powerOutputs->map(
                fn (PowerOutput $powerOutput): array => [
                    'value' => [$powerOutput->getTimeIntervalInSeconds(), $powerOutput->getPower()],
                    'relativePower' => $powerOutput->getRelativePower(),
                ]
            );

            if ([] === $data) {
                continue; // @codeCoverageIgnore
            }

            $powers = $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getPower());
            $minPowerOutput = min($minPowerOutput, ...$powers);
            $maxPowerOutput = max($maxPowerOutput, ...$powers);

            $selectedSeries[$description] = in_array($description, $this->visibleSeries, true);
            $series[] = [
                'type' => 'line',
                'name' => $description,
                'symbol' => 'none',
                'lineStyle' => ['width' => 2],
                'emphasis' => ['focus' => 'series'],
                'data' => $data,
            ];
        }

        $yAxisMinValue = (int) (floor($minPowerOutput / 100) * 100);
        $yAxisMaxValue = (int) (ceil($maxPowerOutput / 100) * 100);

        return [
            'animation' => true,
            'backgroundColor' => null,
            'grid' => [
                'left' => '2px',
                'right' => '10px',
                'top' => '10px',
                'bottom' => '40px',
                'containLabel' => true,
            ],
            'legend' => [
                'show' => true,
                'bottom' => 0,
                'selected' => $selectedSeries,
            ],
            'tooltip' => [
                'show' => true,
                'trigger' => 'axis',
                'formatter' => EChartsCallback::formatPowerDurationTooltip(),
            ],
            'xAxis' => [
                'type' => 'log',
                'min' => 1,
                'max' => 3600,
                'axisLabel' => [
                    'formatter' => EChartsCallback::formatDuration(),
                    'customValues' => self::X_AXIS_TICKS_IN_SECONDS,
                    'hideOverlap' => true,
                ],
                'axisTick' => [
                    'customValues' => self::X_AXIS_TICKS_IN_SECONDS,
                ],
                'splitLine' => [
                    'show' => false,
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'axisLabel' => [
                        'formatter' => '{value} w',
                    ],
                    'min' => $yAxisMinValue,
                    'max' => $yAxisMaxValue,
                    'splitNumber' => 5,
                    'splitLine' => [
                        'lineStyle' => ['type' => 'solid'],
                    ],
                ],
            ],
            'series' => $series,
        ];
    }
}
