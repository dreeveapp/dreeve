<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Theme\Theme;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CombinedStreamProfileCharts
{
    private const int GRID_HEIGHT = 120;
    private const int DATAZOOM_TOP = 30;
    private const int DATAZOOM_HEIGHT = 25;
    private const int TOP_MARGIN = 90;
    private const int BOTTOM_MARGIN = 35;
    private const int GAP = 5;

    /**
     * @param list<array{
     *     yAxisData: array<int, int|float>,
     *     yAxisStreamType: CombinedStreamType,
     * }> $items
     * @param array<int, int|float> $topXAxisData
     * @param array<int, int|float> $bottomXAxisData
     * @param array<int, int|float> $grades
     */
    private function __construct(
        private array $items,
        private array $topXAxisData,
        private array $bottomXAxisData,
        private ?string $bottomXAxisSuffix,
        private array $grades,
        private int $maximumNumberOfDigitsOnYAxis,
        private UnitSystem $unitSystem,
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
     */
    public static function create(
        array $items,
        array $topXAxisData,
        array $bottomXAxisData,
        ?string $bottomXAxisSuffix,
        array $grades,
        int $maximumNumberOfDigitsOnYAxis,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            items: $items,
            topXAxisData: $topXAxisData,
            bottomXAxisData: $bottomXAxisData,
            bottomXAxisSuffix: $bottomXAxisSuffix,
            grades: $grades,
            maximumNumberOfDigitsOnYAxis: $maximumNumberOfDigitsOnYAxis,
            unitSystem: $unitSystem,
            translator: $translator,
        );
    }

    public function getTotalHeight(): int
    {
        $count = count($this->items);

        return self::TOP_MARGIN + $count * self::GRID_HEIGHT + ($count - 1) * self::GAP + self::BOTTOM_MARGIN;
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

        foreach ($this->items as $index => $item) {
            $yAxisData = $item['yAxisData'];
            /** @var CombinedStreamType $yAxisStreamType */
            $yAxisStreamType = $item['yAxisStreamType'];
            $isFirst = 0 === $index;
            $isLast = $index === $count - 1;

            if ([] === $yAxisData) {
                throw new \RuntimeException('yAxisData data cannot be empty');
            }

            $yAxisSuffix = $yAxisStreamType->getSuffix($this->unitSystem);

            [$min, $max] = [min($yAxisData), max($yAxisData)];
            $margin = ($max - $min) * 0.1;
            $maxYAxis = (int) ceil($max + $margin);
            $minYAxis = max($min, 0);

            if (CombinedStreamType::ALTITUDE === $yAxisStreamType) {
                $minYAxis = (int) floor($min - $margin);
            }

            $top = self::TOP_MARGIN + $index * (self::GRID_HEIGHT + self::GAP);

            $grids[] = [
                'left' => $gridLeft,
                'right' => '20px',
                'top' => $top.'px',
                'height' => self::GRID_HEIGHT.'px',
                'containLabel' => false,
            ];

            $xAxisPosition = match (true) {
                $isFirst => Theme::POSITION_TOP,
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
                'gridIndex' => $index,
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
            $xAxisIndices[] = $index;

            $yAxes[] = [
                'gridIndex' => $index,
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

            $series[] = [
                'name' => CombinedStreamType::PACE === $yAxisStreamType ? '__pace' : $yAxisSuffix,
                'xAxisIndex' => $index,
                'yAxisIndex' => $index,
                'markArea' => [
                    'data' => [
                        [
                            [
                                'xAxis' => 'min',
                                'yAxis' => $maxYAxis,
                                'itemStyle' => [
                                    'color' => '#3E444D',
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
                    ],
                ],
                'data' => $seriesData,
                'type' => 'line',
                'showSymbol' => false,
                'progressive' => 5000,
                'progressiveThreshold' => 10000,
                'color' => $yAxisStreamType->getSeriesColor(),
                'smooth' => true,
                'lineStyle' => [
                    'width' => 0,
                ],
                'emphasis' => [
                    'disabled' => true,
                ],
                'areaStyle' => [
                    'opacity' => 1,
                    'origin' => 'start',
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
}
