<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Athlete\HeartRateZone\HeartRateZones;

final readonly class HeartRateDistributionChart
{
    use ProvideSteppedValue;

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
        $maxHeartRate = (int) max(200, ceil(max($heartRates) / 10) * 10);

        foreach (range($minHeartRate, $maxHeartRate) as $heartRate) {
            if (array_key_exists($heartRate, $this->heartRateData)) {
                continue;
            }
            $heartRateData[$heartRate] = 0;
        }
        ksort($heartRateData);

        $step = (int) floor(($maxHeartRate - $minHeartRate) / 24) ?: 1;
        /** @var non-empty-array<int, int> $xAxisValues */
        $xAxisValues = range($minHeartRate, $maxHeartRate, $step);
        if (end($xAxisValues) < $maxHeartRate) {
            $xAxisValues[] = $maxHeartRate;
        }

        $totalTimeInSeconds = array_sum($heartRateData);
        $data = [];
        foreach ($xAxisValues as $axisValue) {
            $data[] = array_sum(array_splice($heartRateData, 0, $step)) / $totalTimeInSeconds * 100;
        }
        $yAxisMax = max($data) * 1.40;
        $xAxisValueAverageHeartRate = array_search($this->findClosestSteppedValue(
            min: $minHeartRate,
            max: $maxHeartRate,
            step: $step,
            target: $this->averageHeartRate
        ), $xAxisValues);
        // Calculate the mark areas to display the zones.
        $oneHeartBeatPercentage = 100 / ($maxHeartRate - $minHeartRate);

        $zoneOneFromPercentage = $this->heartRateZones->getZoneOne()->getFromPercentage($this->athleteMaxHeartRate);
        $beforeZoneOne = (($this->athleteMaxHeartRate * ($zoneOneFromPercentage / 100)) - $minHeartRate) * $oneHeartBeatPercentage;

        $markAreaStep = (100 - $beforeZoneOne - (($maxHeartRate - $this->athleteMaxHeartRate) * $oneHeartBeatPercentage)) / (100 - $zoneOneFromPercentage);

        $zoneRanges = [];
        $zones = [
            $this->heartRateZones->getZoneOne(),
            $this->heartRateZones->getZoneTwo(),
            $this->heartRateZones->getZoneThree(),
            $this->heartRateZones->getZoneFour(),
        ];

        $cumulative = 0;
        /** @var \App\Domain\Athlete\HeartRateZone\HeartRateZone $zone */
        foreach ($zones as $zone) {
            $cumulative += $zone->getDifferenceBetweenFromAndToPercentage($this->athleteMaxHeartRate);
            $zoneRanges[] = $cumulative;
        }

        [$zone1Range, $zone2Range, $zone3Range, $zone4Range] = $zoneRanges;

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
                        'symbol' => 'path://M2 9.1371C2 14 6.01943 16.5914 8.96173 18.9109C10 19.7294 11 20.5 12 20.5C13 20.5 14 19.7294 15.0383 18.9109C17.9806 16.5914 22 14 22 9.1371C22 4.27416 16.4998 0.825464 12 5.50063C7.50016 0.825464 2 4.27416 2 9.1371Z',
                        'symbolSize' => [55, 48],
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
                                'value' => $this->averageHeartRate,
                                'coord' => [$xAxisValueAverageHeartRate, $yAxisMax * 0.8],
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
                                ['xAxis' => $xAxisValueAverageHeartRate, 'yAxis' => 0],
                                ['xAxis' => $xAxisValueAverageHeartRate, 'yAxis' => $yAxisMax * 0.8],
                            ],
                        ],
                        'silent' => true,
                    ],
                    'markArea' => [
                        'data' => [
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
                                    'x' => $beforeZoneOne.'%',
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
                                    'x' => $beforeZoneOne.'%',
                                ],
                                [
                                    'x' => ($beforeZoneOne + ($zone1Range * $markAreaStep)).'%',
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
                                    'x' => ($beforeZoneOne + ($zone1Range * $markAreaStep)).'%',
                                ],
                                [
                                    'x' => ($beforeZoneOne + ($zone2Range * $markAreaStep)).'%',
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
                                    'x' => ($beforeZoneOne + ($zone2Range * $markAreaStep)).'%',
                                ],
                                [
                                    'x' => ($beforeZoneOne + ($zone3Range * $markAreaStep)).'%',
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
                                    'x' => ($beforeZoneOne + ($zone3Range * $markAreaStep)).'%',
                                ],
                                [
                                    'x' => ($beforeZoneOne + ($zone4Range * $markAreaStep)).'%',
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
                                    'x' => ($beforeZoneOne + ($zone4Range * $markAreaStep)).'%',
                                ],
                                [
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
