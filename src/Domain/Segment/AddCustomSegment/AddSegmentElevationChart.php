<?php

declare(strict_types=1);

namespace App\Domain\Segment\AddCustomSegment;

use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class AddSegmentElevationChart
{
    /**
     * @param list<int|float>           $distances
     * @param non-empty-list<int|float> $altitudes
     */
    private function __construct(
        private array $distances,
        private array $altitudes,
        private UnitSystem $unitSystem,
    ) {
    }

    /**
     * @param list<int|float>           $distances
     * @param non-empty-list<int|float> $altitudes
     */
    public static function create(
        array $distances,
        array $altitudes,
        UnitSystem $unitSystem,
    ): self {
        return new self(
            distances: $distances,
            altitudes: $altitudes,
            unitSystem: $unitSystem,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $distanceSymbol = $this->unitSystem->distanceSymbol();
        $elevationSymbol = $this->unitSystem->elevationSymbol();

        $distances = array_map(
            fn (int|float $distance): string => sprintf('%.2f', Meter::from($distance)->toKilometer()->toUnitSystem($this->unitSystem)->toFloat()),
            $this->distances,
        );
        $elevations = array_map(
            fn (int|float $altitude): int => (int) round(Meter::from($altitude)->toUnitSystem($this->unitSystem)->toFloat()),
            $this->altitudes,
        );

        $margin = (max($elevations) - min($elevations)) * 0.1;
        $minYAxis = (int) floor(min($elevations) - $margin);
        $maxYAxis = (int) ceil(max($elevations) + $margin);

        return [
            'animation' => false,
            'grid' => [
                'left' =>'50px',
                'right' => '0',
                'top' => '10px',
                'bottom' => '0',
                'containLabel' => false,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => sprintf('{b0} %s · {c0} %s', $distanceSymbol, $elevationSymbol),
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $distances,
                'axisTick' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'formatter' => [
                        'callback' => 'formatRounded',
                        'args' => [
                            'decimals' => 1,
                            'suffix' => $distanceSymbol,
                        ],
                    ],
                ],
                'splitLine' => [
                    'show' => false,
                ],
            ],
            'yAxis' => [
                'type' => 'value',
                'min' => $minYAxis,
                'max' => $maxYAxis,
                'axisTick' => [
                    'show' => false,
                ],
                'splitLine' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'show' => true,
                    'formatter' => '{value} '.$elevationSymbol,
                    'customValues' => [$minYAxis, $maxYAxis],
                    'color' => '#aaa',
                    'verticalAlignMaxLabel' => 'top',
                    'verticalAlignMinLabel' => 'bottom',
                ],
            ],
            'series' => [
                [
                    'type' => 'line',
                    'data' => $elevations,
                    'color' => CombinedStreamType::ALTITUDE->getSeriesColor(),
                    'smooth' => true,
                    'showSymbol' => false,
                    'lineStyle' => [
                        'width' => 0,
                    ],
                    'areaStyle' => [
                        'opacity' => 1,
                        'origin' => 'start',
                    ],
                    'emphasis' => [
                        'disabled' => true,
                    ],
                ],
            ],
        ];
    }
}
