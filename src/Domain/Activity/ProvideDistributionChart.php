<?php

declare(strict_types=1);

namespace App\Domain\Activity;

trait ProvideDistributionChart
{
    private function findClosestSteppedValue(int $min, int $max, int $step, int|float $target): int
    {
        $stepsFromMin = round(($target - $min) / $step);
        $closest = (int) round($min + ($stepsFromMin * $step));

        if ($closest < $min) {
            return $min;
        }
        if ($closest > $max) {
            return $max;
        }

        return $closest;
    }

    /**
     * @param array<int, int> $distribution
     *
     * @return array{0: int, 1: non-empty-list<int>, 2: non-empty-list<float>}
     */
    private function distributeOverSteps(array $distribution, int $min, int $max): array
    {
        $distribution += array_fill_keys(range($min, $max), 0);
        ksort($distribution);

        $step = (int) floor(($max - $min) / 24) ?: 1;
        $xAxisValues = range($min, $max, $step);
        if (end($xAxisValues) < $max) {
            $xAxisValues[] = $max;
        }

        $totalTimeInSeconds = array_sum($distribution);
        $data = [];
        foreach ($xAxisValues as $axisValue) {
            $data[] = array_sum(array_splice($distribution, 0, $step)) / $totalTimeInSeconds * 100;
        }

        return [$step, $xAxisValues, $data];
    }

    /**
     * @param list<int|string>                    $xAxisValues
     * @param list<float>                         $data
     * @param array<string, mixed>                $markPointSymbol
     * @param list<array<int, array<mixed>>>|null $markAreas
     *
     * @return array<string, mixed>
     */
    private function buildDistributionChart(
        array $xAxisValues,
        array $data,
        float $yAxisMax,
        int|float|string $averageValue,
        int|string|false $averageXAxisIndex,
        array $markPointSymbol,
        float $markPointHeight,
        ?array $markAreas = null,
    ): array {
        return [
            'grid' => [
                'left' => '1%',
                'right' => '1%',
                'bottom' => '7%',
                'height' => '325px',
                'containLabel' => false,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $xAxisValues,
                'axisTick' => [
                    'show' => false,
                ],
                'axisLine' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'interval' => 2,
                    'showMinLabel' => true,
                ],
            ],
            'yAxis' => [
                'show' => false,
                'min' => 0,
                'max' => $yAxisMax,
            ],
            'series' => [
                [
                    'data' => $data,
                    'type' => 'bar',
                    'cursor' => 'default',
                    'barCategoryGap' => 1,
                    'itemStyle' => [
                        'color' => '#fff',
                        'borderRadius' => [25, 25, 0, 0],
                    ],
                    'markPoint' => [
                        'silent' => true,
                        'animation' => false,
                        ...$markPointSymbol,
                        'itemStyle' => [
                            'color' => '#7F7F7F',
                        ],
                        'label' => [
                            'formatter' => "{label|AVG}\n{sub|{c}}",
                            'lineHeight' => 15,
                            'rich' => [
                                'label' => [
                                    'fontWeight' => 'bold',
                                ],
                                'sub' => [
                                    'fontSize' => 12,
                                ],
                            ],
                        ],
                        'data' => [
                            [
                                'value' => $averageValue,
                                'coord' => [$averageXAxisIndex, $yAxisMax * $markPointHeight],
                            ],
                        ],
                    ],
                    'markLine' => [
                        'symbol' => 'none',
                        'animation' => false,
                        'lineStyle' => [
                            'type' => 'solid',
                            'width' => 2,
                            'color' => '#7F7F7F',
                        ],
                        'label' => [
                            'show' => false,
                        ],
                        'data' => [
                            [
                                ['xAxis' => $averageXAxisIndex, 'yAxis' => 0],
                                ['xAxis' => $averageXAxisIndex, 'yAxis' => $yAxisMax * $markPointHeight],
                            ],
                        ],
                        'silent' => true,
                    ],
                    'markArea' => [
                        'data' => $markAreas ?? [
                            [
                                [
                                    'itemStyle' => [
                                        'color' => '#3E444D',
                                    ],
                                    'emphasis' => [
                                        'disabled' => true,
                                    ],
                                ],
                                [
                                    'x' => '100%',
                                ],
                            ],
                        ],
                        'silent' => true,
                    ],
                ],
            ],
        ];
    }
}
