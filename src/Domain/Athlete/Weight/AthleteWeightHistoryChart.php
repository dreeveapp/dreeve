<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight;

use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AthleteWeightHistoryChart
{
    private function __construct(
        /** @var AthleteWeight[] */
        private array $athleteWeights,
        private SerializableDateTime $now,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param AthleteWeight[] $athleteWeights
     */
    public static function create(
        array $athleteWeights,
        SerializableDateTime $now,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            athleteWeights: $athleteWeights,
            now: $now,
            unitSystem: $unitSystem,
            translator: $translator
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $athleteWeights = $this->athleteWeights;
        ksort($athleteWeights);
        /** @var non-empty-array<float> $weights */
        $weights = array_map(
            fn (AthleteWeight $athleteWeight): float => round($athleteWeight->getWeight()->toFloat(), 1),
            $athleteWeights,
        );

        $firstAthleteWeight = reset($athleteWeights);
        assert($firstAthleteWeight instanceof AthleteWeight);

        $zoomEndValue = $this->now->format('Y-m-d');
        $zoomStartValue = $firstAthleteWeight->getOn()->format('Y-m-d');
        if (count($athleteWeights) >= 2) {
            // Zoom in on the current year by default, unless there are less than 2 data points.
            // Make sure there are at least 3 data points visible.
            $zoomStartValue = $this->now->format('Y-01-01');

            $weightsInCurrentYear = array_filter(
                $athleteWeights,
                fn (AthleteWeight $weight): bool => $weight->getOn()->format('Y-m-d') >= $zoomStartValue
            );

            if (count($weightsInCurrentYear) < 3) {
                $dates = array_map(fn (AthleteWeight $weight): string => $weight->getOn()->format('Y-m-d'), $athleteWeights);
                $zoomStartValue = array_slice($dates, -3, 1)[0];
            }
        }

        /** @var string $lastKey */
        $lastKey = array_key_last($athleteWeights);

        return [
            'animation' => true,
            'backgroundColor' => null,
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => 'callback:formatDateOnlyTooltip',
            ],
            'grid' => [
                'top' => '2%',
                'left' => '3%',
                'right' => '4%',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'toolbox' => [
                'show' => true,
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
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'startValue' => $zoomStartValue,
                    'endValue' => $zoomEndValue,
                    'brushSelect' => false,
                    'zoomLock' => false,
                ],
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
                            'day' => '',
                            'hour' => '{HH}:{mm}',
                            'minute' => '{HH}:{mm}',
                            'second' => '{HH}:{mm}:{ss}',
                            'millisecond' => '{hh}:{mm}:{ss} {SSS}',
                            'none' => '{yyyy}-{MM}-{dd}',
                        ],
                    ],
                    'splitLine' => [
                        'show' => true,
                        'lineStyle' => [
                            'color' => '#E0E6F1',
                        ],
                    ],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'splitLine' => [
                        'show' => false,
                    ],
                    'axisLabel' => [
                        'formatter' => '{value} '.$this->unitSystem->weightSymbol(),
                    ],
                    'min' => floor(min($weights) / 10) * 10,
                ],
            ],
            'series' => [
                [
                    'name' => $this->translator->trans('Weight'),
                    'color' => [
                        '#E34902',
                    ],
                    'type' => 'line',
                    'smooth' => false,
                    'label' => [
                        'show' => false,
                    ],
                    'lineStyle' => [
                        'width' => 1,
                    ],
                    'symbolSize' => 6,
                    'showSymbol' => true,
                    'data' => [
                        ...array_map(
                            fn (AthleteWeight $weight): array => [
                                $weight->getOn()->format('Y-m-d'),
                                round($weight->getWeight()->toFloat(), 1),
                            ],
                            $athleteWeights,
                        ),
                        $this->now->format('Y-m-d') != $athleteWeights[$lastKey]->getOn()->format('Y-m-d') ?
                        [
                            $this->now->format('Y-m-d'),
                            round($athleteWeights[$lastKey]->getWeight()->toFloat(), 1),
                        ] : [],
                    ],
                ],
            ],
        ];
    }
}
