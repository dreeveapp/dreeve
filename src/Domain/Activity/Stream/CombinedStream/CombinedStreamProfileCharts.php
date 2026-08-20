<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Athlete\HeartRateZone\HeartRateZones;
use App\Domain\Theme\Theme;
use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CombinedStreamProfileCharts
{
    private const int GRID_HEIGHT = 120;
    private const int DATAZOOM_TOP = 30;
    private const int DATAZOOM_HEIGHT = 25;
    private const int TOP_MARGIN = 90;
    private const int BOTTOM_MARGIN = 35;
    private const int GAP = 5;
    private const int RIBBON_HEIGHT = 18;
    private const string LANE_BACKGROUND_COLOR = '#3E444D';
    private const float ZONE_BAND_OPACITY = 0.35;

    /**
     * @param list<array{
     *     yAxisData: array<int, int|float>,
     *     yAxisStreamType: CombinedStreamType,
     * }> $items
     * @param array<int, int|float> $topXAxisData
     * @param array<int, int|float> $bottomXAxisData
     * @param array<int, int|float> $grades
     * @param array<int, int|float> $temperatures
     */
    private function __construct(
        private array $items,
        private array $topXAxisData,
        private array $bottomXAxisData,
        private ?string $bottomXAxisSuffix,
        private array $grades,
        private array $temperatures,
        private int $maximumNumberOfDigitsOnYAxis,
        private UnitSystem $unitSystem,
        private SportType $sportType,
        private int $athleteMaxHeartRate,
        private HeartRateZones $heartRateZones,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<array{
     *     yAxisData: array<int, int|float>,
     *     yAxisStreamType: CombinedStreamType,
     * }> $items
     * @param array<int, int|float> $topXAxisData
     * @param array<int, int|float> $bottomXAxisData
     * @param array<int, int|float> $grades
     * @param array<int, int|float> $temperatures
     */
    public static function create(
        array $items,
        array $topXAxisData,
        array $bottomXAxisData,
        ?string $bottomXAxisSuffix,
        array $grades,
        array $temperatures,
        int $maximumNumberOfDigitsOnYAxis,
        UnitSystem $unitSystem,
        SportType $sportType,
        int $athleteMaxHeartRate,
        HeartRateZones $heartRateZones,
        TranslatorInterface $translator,
    ): self {
        return new self(
            items: $items,
            topXAxisData: $topXAxisData,
            bottomXAxisData: $bottomXAxisData,
            bottomXAxisSuffix: $bottomXAxisSuffix,
            grades: $grades,
            temperatures: $temperatures,
            maximumNumberOfDigitsOnYAxis: $maximumNumberOfDigitsOnYAxis,
            unitSystem: $unitSystem,
            sportType: $sportType,
            athleteMaxHeartRate: $athleteMaxHeartRate,
            heartRateZones: $heartRateZones,
            translator: $translator,
        );
    }

    public static function totalHeightFor(int $numberOfLanes, bool $hasTemperatureRibbon = false): int
    {
        return self::TOP_MARGIN + $numberOfLanes * self::GRID_HEIGHT
            + ($numberOfLanes - 1) * self::GAP + self::BOTTOM_MARGIN
            + ($hasTemperatureRibbon ? self::RIBBON_HEIGHT + self::GAP : 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $count = count($this->items);
        $grids = [];
        $xAxes = [];
        $yAxes = [];
        $series = [];
        $xAxisIndices = [];

        $gridLeft = match ($this->maximumNumberOfDigitsOnYAxis) {
            4 => '75px',
            5 => '85px',
            default => '65px',
        };

        $hasRibbon = [] !== $this->temperatures;
        $gridIndexOffset = $hasRibbon ? 1 : 0;
        $topOffset = $hasRibbon ? self::RIBBON_HEIGHT + self::GAP : 0;

        if ([] !== $this->temperatures) {
            $ribbon = $this->buildTemperatureRibbon($gridLeft, $this->temperatures);
            $grids[] = $ribbon['grid'];
            $xAxes[] = $ribbon['xAxis'];
            $yAxes[] = $ribbon['yAxis'];
            $series[] = $ribbon['series'];
            $xAxisIndices[] = 0;
        }

        foreach ($this->items as $index => $item) {
            $yAxisData = $item['yAxisData'];
            /** @var CombinedStreamType $yAxisStreamType */
            $yAxisStreamType = $item['yAxisStreamType'];
            $isFirst = 0 === $index;
            $isLast = $index === $count - 1;

            if ([] === $yAxisData) {
                throw new \RuntimeException('yAxisData data cannot be empty');
            }

            $yAxisSuffix = $yAxisStreamType->getSuffix($this->unitSystem, $this->sportType);
            $isHeartRateStreamType = CombinedStreamType::HEART_RATE === $yAxisStreamType;

            [$min, $max] = [min($yAxisData), max($yAxisData)];
            $margin = ($max - $min) * 0.1;
            $maxYAxis = (int) ceil($max + $margin);
            $minYAxis = max($min, 0);

            if (CombinedStreamType::ALTITUDE === $yAxisStreamType) {
                $minYAxis = (int) floor($min - $margin);
            }

            $gridIndex = $index + $gridIndexOffset;
            $top = self::TOP_MARGIN + $topOffset + $index * (self::GRID_HEIGHT + self::GAP);

            $grids[] = [
                'left' => $gridLeft,
                'right' => '20px',
                'top' => $top.'px',
                'height' => self::GRID_HEIGHT.'px',
                'containLabel' => false,
            ];

            $xAxisPosition = match (true) {
                $isFirst && !$hasRibbon => Theme::POSITION_TOP,
                $isLast => Theme::POSITION_BOTTOM,
                default => null,
            };
            $xAxisData = match (true) {
                $isLast && [] !== $this->bottomXAxisData => $this->bottomXAxisData,
                default => $this->topXAxisData,
            };
            $xAxisLabelSuffix = match (true) {
                $isLast && [] !== $this->bottomXAxisData => $this->bottomXAxisSuffix,
                default => null,
            };

            $xAxes[] = [
                'gridIndex' => $gridIndex,
                'position' => $xAxisPosition,
                'type' => 'category',
                'boundaryGap' => false,
                'axisLabel' => [
                    'show' => !is_null($xAxisPosition),
                    'formatter' => '{value} '.$xAxisLabelSuffix,
                ],
                'data' => $xAxisData,
                'min' => 0,
                'axisTick' => [
                    'show' => false,
                ],
            ];
            $xAxisIndices[] = $gridIndex;

            $yAxes[] = [
                'gridIndex' => $gridIndex,
                'type' => 'value',
                'name' => $yAxisStreamType->trans($this->translator),
                'nameRotate' => 90,
                'nameLocation' => 'middle',
                'nameGap' => 10,
                'min' => $minYAxis,
                'max' => $maxYAxis,
                'splitLine' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'show' => true,
                    'customValues' => [$minYAxis, $maxYAxis],
                    'formatter' => CombinedStreamType::PACE === $yAxisStreamType ? 'callback:formatSecondsTrimZero' : null,
                    'color' => '#aaa',
                    'verticalAlignMaxLabel' => 'top',
                    'verticalAlignMinLabel' => 'bottom',
                ],
            ];

            $seriesData = $yAxisData;
            if (CombinedStreamType::ALTITUDE === $yAxisStreamType && [] !== $this->grades) {
                $seriesData = array_map(
                    fn (int|float $elevation, int|float $grade): array => ['value' => $elevation, 'extra' => $grade.'%'],
                    $yAxisData,
                    $this->grades,
                );
            }
            if ($isHeartRateStreamType) {
                $zones = [];
                foreach ($this->heartRateZones->getZones() as $i => $heartRateZone) {
                    $zones[] = [
                        'fromBpm' => $heartRateZone->getRangeInBpm($this->athleteMaxHeartRate)[0],
                        'name' => sprintf('zone %d', $i + 1),
                    ];
                }
                $zones = array_reverse($zones);
                $seriesData = array_map(
                    function (int|float $heartRate) use ($zones): int|float|array {
                        foreach ($zones as $zone) {
                            if ($heartRate >= $zone['fromBpm']) {
                                return ['value' => $heartRate, 'extra' => $zone['name']];
                            }
                        }

                        return $heartRate;
                    },
                    $yAxisData
                );
            }

            $series[] = [
                'name' => CombinedStreamType::PACE === $yAxisStreamType ? '__pace' : $yAxisSuffix,
                'xAxisIndex' => $gridIndex,
                'yAxisIndex' => $gridIndex,
                'markArea' => $this->buildMarkArea(
                    streamType: $yAxisStreamType,
                    minYAxis: $minYAxis,
                    maxYAxis: $maxYAxis
                ),
                'data' => $seriesData,
                'type' => 'line',
                'showSymbol' => false,
                'progressive' => 5000,
                'progressiveThreshold' => 10000,
                'color' => $yAxisStreamType->getSeriesColor(),
                'smooth' => true,
                'lineStyle' => [
                    'width' => $isHeartRateStreamType ? 2 : 0,
                ],
                'emphasis' => [
                    'disabled' => true,
                ],
                ...$isHeartRateStreamType ? [] : [
                    'areaStyle' => [
                        'opacity' => 1,
                        'origin' => 'start',
                    ],
                ],
            ];
        }

        return [
            'animation' => false,
            'axisPointer' => [
                'link' => [
                    ['xAxisIndex' => 'all'],
                ],
            ],
            'toolbox' => [
                'show' => true,
                'top' => '0px',
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
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => 'callback:formatCombinedProfileTooltip',
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'show' => true,
                    'top' => self::DATAZOOM_TOP.'px',
                    'height' => self::DATAZOOM_HEIGHT.'px',
                    'left' => $gridLeft,
                    'right' => '20px',
                    'xAxisIndex' => $xAxisIndices,
                    'throttle' => 100,
                    'showDetail' => false,
                    'minValueSpan' => 60,
                    'dataBackground' => [
                        'lineStyle' => [
                            'opacity' => 0,
                        ],
                        'areaStyle' => [
                            'opacity' => 0,
                        ],
                    ],
                    'selectedDataBackground' => [
                        'lineStyle' => [
                            'opacity' => 0,
                        ],
                        'areaStyle' => [
                            'opacity' => 0,
                        ],
                    ],
                ],
            ],
            'grid' => $grids,
            'xAxis' => $xAxes,
            'yAxis' => $yAxes,
            'series' => $series,
        ];
    }

    /**
     * @param non-empty-array<int, int|float> $temperatures
     *
     * @return array{
     *     grid: array<string, mixed>,
     *     xAxis: array<string, mixed>,
     *     yAxis: array<string, mixed>,
     *     series: array<string, mixed>,
     * }
     */
    private function buildTemperatureRibbon(string $gridLeft, array $temperatures): array
    {
        [$min, $max] = [min($temperatures), max($temperatures)];
        $margin = ($max - $min) * 0.1;
        $minYAxis = (int) floor($min - $margin);
        $maxYAxis = (int) ceil($max + $margin);
        if ($minYAxis === $maxYAxis) {
            --$minYAxis;
            ++$maxYAxis;
        }

        return [
            'grid' => [
                'left' => $gridLeft,
                'right' => '20px',
                'top' => self::TOP_MARGIN.'px',
                'height' => self::RIBBON_HEIGHT.'px',
                'containLabel' => false,
            ],
            'xAxis' => [
                'gridIndex' => 0,
                'position' => Theme::POSITION_TOP,
                'type' => 'category',
                'boundaryGap' => false,
                'axisLabel' => [
                    'show' => true,
                    'formatter' => '{value} ',
                ],
                'data' => $this->topXAxisData,
                'min' => 0,
                'axisTick' => [
                    'show' => false,
                ],
                'axisLine' => [
                    'show' => false,
                ],
            ],
            'yAxis' => [
                'gridIndex' => 0,
                'type' => 'value',
                'name' => CombinedStreamType::TEMP->getSuffix(
                    unitSystem: $this->unitSystem,
                    sportType: $this->sportType
                ),
                'nameLocation' => 'middle',
                'nameGap' => 22,
                'nameTextStyle' => [
                    'color' => '#aaa',
                    'fontSize' => 11,
                ],
                'min' => $minYAxis,
                'max' => $maxYAxis,
                'splitLine' => [
                    'show' => false,
                ],
                'axisLine' => [
                    'show' => false,
                ],
                'axisTick' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'show' => false,
                ],
            ],
            'series' => [
                'name' => CombinedStreamType::TEMP->getSuffix(
                    unitSystem: $this->unitSystem,
                    sportType: $this->sportType
                ),
                'xAxisIndex' => 0,
                'yAxisIndex' => 0,
                'data' => $temperatures,
                'type' => 'line',
                'showSymbol' => false,
                'progressive' => 5000,
                'progressiveThreshold' => 10000,
                'smooth' => true,
                'color' => CombinedStreamType::TEMP->getSeriesColor(),
                'lineStyle' => [
                    'width' => 2,
                ],
                'emphasis' => [
                    'disabled' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMarkArea(
        CombinedStreamType $streamType,
        int|float $minYAxis,
        int|float $maxYAxis,
    ): array {
        $data = [
            [
                [
                    'xAxis' => 'min',
                    'yAxis' => $maxYAxis,
                    'itemStyle' => [
                        'color' => self::LANE_BACKGROUND_COLOR,
                    ],
                    'emphasis' => [
                        'disabled' => true,
                    ],
                ],
                [
                    'xAxis' => 'max',
                    'yAxis' => $minYAxis,
                ],
            ],
        ];

        if (CombinedStreamType::HEART_RATE !== $streamType) {
            return ['data' => $data];
        }

        $zones = $this->heartRateZones->getZones();
        foreach ($zones as $i => $heartRateZone) {
            // A zone ends one beat below the next one.
            $nextZone = $zones[$i + 1] ?? null;
            $upperBpm = is_null($nextZone) ? $maxYAxis : $nextZone->getRangeInBpm($this->athleteMaxHeartRate)[0];
            $from = max($heartRateZone->getRangeInBpm($this->athleteMaxHeartRate)[0], $minYAxis);
            $to = min($upperBpm, $maxYAxis);
            if ($from >= $to) {
                continue;
            }

            $data[] = [
                [
                    'xAxis' => 'min',
                    'yAxis' => $to,
                    'itemStyle' => [
                        'color' => $heartRateZone->getColor(),
                        'opacity' => self::ZONE_BAND_OPACITY,
                    ],
                    'emphasis' => [
                        'disabled' => true,
                    ],
                ],
                [
                    'xAxis' => 'max',
                    'yAxis' => $from,
                ],
            ];
        }

        return ['data' => $data];
    }
}
