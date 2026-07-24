<?php

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SegmentEffortVsHeartRateChart
{
    /** @var array{int, int, int} */
    private const array COLOR_OLDEST = [212, 212, 212];
    /** @var array{int, int, int} */
    private const array COLOR_RECENT = [227, 73, 2];

    private function __construct(
        private SegmentEfforts $segmentEfforts,
        private SportType $sportType,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        SegmentEfforts $segmentEfforts,
        SportType $sportType,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            segmentEfforts: $segmentEfforts,
            sportType: $sportType,
            unitSystem: $unitSystem,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $effortsWithHeartRate = array_values(array_filter(
            $this->segmentEfforts->toArray(),
            fn (SegmentEffort $effort): bool => null !== $effort->getAverageHeartRate(),
        ));

        if ([] === $effortsWithHeartRate) {
            return [];
        }

        $count = count($effortsWithHeartRate);
        $minElapsedTime = PHP_FLOAT_MAX;
        $maxElapsedTime = PHP_FLOAT_MIN;
        $minHeartRate = PHP_INT_MAX;
        $maxHeartRate = PHP_INT_MIN;

        $preference = $this->sportType->getVelocityDisplayPreference();
        $velocityIsPace = $preference instanceof SecPer100Meter || $preference instanceof SecPerKm;
        $velocityUnit = match (true) {
            $preference instanceof SecPer100Meter => '/'.SecPerKm::zero()->getSymbol(),
            $preference instanceof SecPerKm => $this->unitSystem->paceSymbol(),
            default => $this->unitSystem->speedSymbol(),
        };

        $data = [];
        foreach ($effortsWithHeartRate as $index => $effort) {
            $velocity = match (true) {
                $preference instanceof SecPer100Meter => round($effort->getPaceInSecPer100Meter()->toFloat(), 1),
                $preference instanceof SecPerKm => round($effort->getPaceInSecPerKm()->toFloat()),
                default => round($effort->getAverageSpeed()->toUnitSystem($this->unitSystem)->toFloat(), 1),
            };
            $heartRate = $effort->getAverageHeartRate();

            $elapsedTime = $effort->getElapsedTimeInSeconds();
            $minElapsedTime = min($minElapsedTime, $elapsedTime);
            $maxElapsedTime = max($maxElapsedTime, $elapsedTime);
            $minHeartRate = min($minHeartRate, $heartRate);
            $maxHeartRate = max($maxHeartRate, $heartRate);

            $ratio = $count > 1 ? 1 - $index / ($count - 1) : 1.0;

            $data[] = [
                'value' => [
                    $heartRate,
                    $velocity,
                    $elapsedTime,
                    $effort->getStartDateTime()->format('Y-m-d'),
                    $velocityIsPace,
                    $velocityUnit,
                ],
                'itemStyle' => [
                    'color' => $this->interpolateColor($ratio),
                ],
            ];
        }

        return [
            'animation' => false,
            'grid' => [
                'top' => '30px',
                'left' => '10px',
                'right' => '30px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'show' => true,
                'trigger' => 'item',
                'formatter' => 'callback:formatEffortVsHeartRateTooltip',
            ],
            'xAxis' => [
                [
                    'type' => 'value',
                    'name' => $this->translator->trans('Heart rate'),
                    'nameLocation' => 'middle',
                    'nameGap' => 25,
                    'min' => max(0, floor($minHeartRate / 5) * 5),
                    'max' => ceil($maxHeartRate / 5) * 5,
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'min' => max(0, floor($minElapsedTime / 5) * 5),
                    'max' => ceil($maxElapsedTime / 5) * 5,
                    'axisLabel' => [
                        'formatter' => 'callback:formatSecondsTrimZero',
                    ],
                ],
            ],
            'visualMap' => [
                'show' => true,
                'type' => 'continuous',
                'seriesIndex' => -1,
                'min' => 0,
                'max' => 1,
                'text' => [
                    $this->translator->trans('Recent effort'),
                    $this->translator->trans('Older effort'),
                ],
                'calculable' => false,
                'hoverLink' => false,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => '0',
                'itemWidth' => 12,
                'itemHeight' => 100,
                'inRange' => [
                    'color' => ['#d4d4d4', '#E34902'],
                ],
                'textStyle' => [
                    'fontSize' => 11,
                ],
            ],
            'series' => [
                [
                    'type' => 'scatter',
                    'symbolSize' => 10,
                    'data' => $data,
                    'encode' => [
                        'x' => 0,
                        'y' => 2,
                    ],
                ],
            ],
            'toolbox' => [
                'show' => true,
                'feature' => [
                    'dataZoom' => [
                        'yAxisIndex' => 'none',
                    ],
                    'restore' => [
                        'show' => true,
                    ],
                ],
            ],
            'dataZoom' => [
                ['type' => 'inside', 'zoomOnMouseWheel' => false, 'minValueSpan' => 1, 'xAxisIndex' => 0],
                ['type' => 'inside', 'zoomOnMouseWheel' => false, 'minValueSpan' => 1, 'yAxisIndex' => 0],
            ],
        ];
    }

    private function interpolateColor(float $ratio): string
    {
        $r = (int) round(self::COLOR_OLDEST[0] + (self::COLOR_RECENT[0] - self::COLOR_OLDEST[0]) * $ratio);
        $g = (int) round(self::COLOR_OLDEST[1] + (self::COLOR_RECENT[1] - self::COLOR_OLDEST[1]) * $ratio);
        $b = (int) round(self::COLOR_OLDEST[2] + (self::COLOR_RECENT[2] - self::COLOR_OLDEST[2]) * $ratio);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
