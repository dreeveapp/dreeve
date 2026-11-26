<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Athlete\HeartRateZone\HeartRateZones;

final readonly class HeartRateDistributionChart
{
    use ProvideDistributionChart;

    private function __construct(
        /** @var array<int, int> */
        private array $heartRateData,
        private int $averageHeartRate,
        private int $athleteMaxHeartRate,
        private HeartRateZones $heartRateZones,
    ) {
    }

    /**
     * @param array<int, int> $heartRateData
     */
    public static function create(
        array $heartRateData,
        int $averageHeartRate,
        int $athleteMaxHeartRate,
        HeartRateZones $heartRateZones,
    ): self {
        return new self(
            heartRateData: $heartRateData,
            averageHeartRate: $averageHeartRate,
            athleteMaxHeartRate: $athleteMaxHeartRate,
            heartRateZones: $heartRateZones
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Calculate all data related things.
        $heartRateData = $this->heartRateData;
        /** @var non-empty-array<int, int> $heartRateData */
        $heartRates = array_keys($heartRateData);
        $minHeartRate = (int) min(60, floor(min($heartRates) / 10) * 10);
        $maxHeartRate = (int) max(ceil($this->athleteMaxHeartRate / 10) * 10, ceil(max($heartRates) / 10) * 10);

        [$step, $xAxisValues, $data] = $this->distributeOverSteps($heartRateData, $minHeartRate, $maxHeartRate);
        $yAxisMax = max($data) * 1.40;
        $xAxisValueAverageHeartRate = array_search($this->findClosestSteppedValue(
            min: $minHeartRate,
            max: $maxHeartRate,
            step: $step,
            target: $this->averageHeartRate
        ), $xAxisValues, true);
        $axisBpmRange = $step * count($xAxisValues);
        $zoneStartPositions = [];
        foreach ($this->heartRateZones->getZones() as $zone) {
            $zoneStartPositions[] = 1 + 98 * min(1, max(0, ($zone->getRangeInBpm($this->athleteMaxHeartRate)[0] - $minHeartRate) / $axisBpmRange));
        }

        [$zoneOneStart, $zoneTwoStart, $zoneThreeStart, $zoneFourStart, $zoneFiveStart] = $zoneStartPositions;

        return $this->buildDistributionChart(
            xAxisValues: $xAxisValues,
            data: $data,
            yAxisMax: $yAxisMax,
            averageValue: $this->averageHeartRate,
            averageXAxisIndex: $xAxisValueAverageHeartRate,
            markPointSymbol: [
                'symbol' => 'path://M2 9.1371C2 14 6.01943 16.5914 8.96173 18.9109C10 19.7294 11 20.5 12 20.5C13 20.5 14 19.7294 15.0383 18.9109C17.9806 16.5914 22 14 22 9.1371C22 4.27416 16.4998 0.825464 12 5.50063C7.50016 0.825464 2 4.27416 2 9.1371Z',
                'symbolSize' => [55, 48],
            ],
            markPointHeight: 0.8,
            markAreas: [
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
                        'x' => $zoneOneStart.'%',
                    ],
                ],
                [
                    [
                        'name' => sprintf(
                            "Zone 1\n%s%% - %s%%",
                            $this->heartRateZones->getZoneOne()->getFromPercentage($this->athleteMaxHeartRate),
                            $this->heartRateZones->getZoneOne()->getToPercentage($this->athleteMaxHeartRate)
                        ),
                        'label' => [
                            'position' => 'insideTop',
                            'fontWeight' => 'bold',
                            'fontSize' => 10,
                            'textAlign' => 'center',
                            'distance' => 12,
                            'textBorderColor' => 'none',
                        ],
                        'itemStyle' => [
                            'color' => $this->heartRateZones->getZoneOne()->getColor(),
                        ],
                        'x' => $zoneOneStart.'%',
                    ],
                    [
                        'x' => $zoneTwoStart.'%',
                    ],
                ],
                [
                    [
                        'name' => sprintf(
                            "Zone 2\n%s%% - %s%%",
                            $this->heartRateZones->getZoneTwo()->getFromPercentage($this->athleteMaxHeartRate),
                            $this->heartRateZones->getZoneTwo()->getToPercentage($this->athleteMaxHeartRate)
                        ),
                        'label' => [
                            'position' => 'insideTop',
                            'fontWeight' => 'bold',
                            'fontSize' => 10,
                            'textAlign' => 'center',
                            'distance' => 12,
                            'textBorderColor' => 'none',
                        ],
                        'itemStyle' => [
                            'color' => $this->heartRateZones->getZoneTwo()->getColor(),
                        ],
                        'x' => $zoneTwoStart.'%',
                    ],
                    [
                        'x' => $zoneThreeStart.'%',
                    ],
                ],
                [
                    [
                        'name' => sprintf(
                            "Zone 3\n%s%% - %s%%",
                            $this->heartRateZones->getZoneThree()->getFromPercentage($this->athleteMaxHeartRate),
                            $this->heartRateZones->getZoneThree()->getToPercentage($this->athleteMaxHeartRate)
                        ),
                        'label' => [
                            'position' => 'insideTop',
                            'fontWeight' => 'bold',
                            'fontSize' => 10,
                            'textAlign' => 'center',
                            'distance' => 12,
                            'textBorderColor' => 'none',
                        ],
                        'itemStyle' => [
                            'color' => $this->heartRateZones->getZoneThree()->getColor(),
                        ],
                        'x' => $zoneThreeStart.'%',
                    ],
                    [
                        'x' => $zoneFourStart.'%',
                    ],
                ],
                [
                    [
                        'name' => sprintf(
                            "Zone 4\n%s%% - %s%%",
                            $this->heartRateZones->getZoneFour()->getFromPercentage($this->athleteMaxHeartRate),
                            $this->heartRateZones->getZoneFour()->getToPercentage($this->athleteMaxHeartRate)
                        ),
                        'label' => [
                            'position' => 'insideTop',
                            'fontWeight' => 'bold',
                            'fontSize' => 10,
                            'textAlign' => 'center',
                            'distance' => 12,
                            'textBorderColor' => 'none',
                        ],
                        'itemStyle' => [
                            'color' => $this->heartRateZones->getZoneFour()->getColor(),
                        ],
                        'x' => $zoneFourStart.'%',
                    ],
                    [
                        'x' => $zoneFiveStart.'%',
                    ],
                ],
                [
                    [
                        'name' => sprintf(
                            "Zone 5\n> %s%%",
                            $this->heartRateZones->getZoneFive()->getFromPercentage($this->athleteMaxHeartRate)
                        ),
                        'label' => [
                            'position' => 'insideTop',
                            'fontWeight' => 'bold',
                            'fontSize' => 10,
                            'textAlign' => 'center',
                            'distance' => 12,
                            'textBorderColor' => 'none',
                        ],
                        'itemStyle' => [
                            'color' => $this->heartRateZones->getZoneFive()->getColor(),
                        ],
                        'x' => $zoneFiveStart.'%',
                    ],
                    [
                    ],
                ],
            ],
        );
    }
}
