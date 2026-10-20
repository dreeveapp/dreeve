<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Ftp\Ftp;

final readonly class PowerDistributionChart
{
    use ProvideDistributionChart;

    private function __construct(
        /** @var array<int, int> */
        private array $powerData,
        private int $averagePower,
        private ?Ftp $ftp,
    ) {
    }

    /**
     * @param array<int, int> $powerData
     */
    public static function create(
        array $powerData,
        int $averagePower,
        ?Ftp $ftp,
    ): self {
        return new self(
            powerData: $powerData,
            averagePower: $averagePower,
            ftp: $ftp,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): ?array
    {
        // Filter out any possible spikes. Data points with less than 2 occurrences are filtered out.
        if (!$powerData = array_filter($this->powerData, fn (int $distribution): bool => $distribution > 2)) {
            return null;
        }
        /** @var non-empty-array<int, int> $powerData */
        $powers = array_keys($powerData);
        $minPower = 0;
        $maxPower = (int) ceil(max($powers) / 100) * 100;

        if ($maxPower <= 0) {
            // Something fishy is going on.
            return null;
        }

        [$step, $xAxisValues, $data] = $this->distributeOverSteps($powerData, $minPower, $maxPower);

        $yAxisMax = max($data) * 1.2;
        $xAxisValueAveragePower = array_search($this->findClosestSteppedValue(
            min: $minPower,
            max: $maxPower,
            step: $step,
            target: $this->averagePower,
        ), $xAxisValues);

        $markAreas = null;
        // Calculate the mark areas to display the zones.
        if ($ftp = $this->ftp?->getFtp()->getValue()) {
            $oneWattPercentage = 100 / $maxPower;

            $markAreas = [
                [
                    [
                        'itemStyle' => [
                            'color' => '#3E444D',
                        ],
                        'emphasis' => [
                            'disabled' => true,
                        ],
                        'x' => '0%',
                    ],
                    [
                        'x' => $ftp * 0.55 * $oneWattPercentage.'%',
                    ],
                ],
                [
                    [
                        'itemStyle' => [
                            'color' => '#2888FC',
                        ],
                        'x' => $ftp * 0.55 * $oneWattPercentage.'%',
                    ],
                    [
                        'x' => $ftp * 0.75 * $oneWattPercentage.'%',
                    ],
                ],
                [
                    [
                        'itemStyle' => [
                            'color' => '#56C15A',
                        ],
                        'x' => $ftp * 0.75 * $oneWattPercentage.'%',
                    ],
                    [
                        'x' => $ftp * 0.87 * $oneWattPercentage.'%',
                    ],
                ],
                [
                    [
                        'itemStyle' => [
                            'color' => '#FECE38',
                        ],
                        'x' => $ftp * 0.87 * $oneWattPercentage.'%',
                    ],
                    [
                        'x' => $ftp * 0.94 * $oneWattPercentage.'%',
                    ],
                ],
                [
                    [
                        'itemStyle' => [
                            'color' => '#FF6531',
                        ],
                        'x' => $ftp * 0.94 * $oneWattPercentage.'%',
                    ],
                    [
                        'x' => $ftp * 1.05 * $oneWattPercentage.'%',
                    ],
                ],
                [
                    [
                        'itemStyle' => [
                            'color' => '#FF2F04',
                        ],
                        'x' => $ftp * 1.05 * $oneWattPercentage.'%',
                    ],
                    [
                        'x' => $ftp * 10000 * $oneWattPercentage.'%',
                    ],
                ],
            ];
        }

        return $this->buildDistributionChart(
            xAxisValues: $xAxisValues,
            data: $data,
            yAxisMax: $yAxisMax,
            averageValue: $this->averagePower,
            averageXAxisIndex: $xAxisValueAveragePower,
            markPointSymbol: ['symbolSize' => 45, 'symbol' => 'roundRect'],
            markPointHeight: 0.9,
            markAreas: $markAreas,
        );
    }
}
