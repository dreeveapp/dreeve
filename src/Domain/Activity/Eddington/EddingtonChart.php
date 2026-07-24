<?php

namespace App\Domain\Activity\Eddington;

use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EddingtonChart
{
    private function __construct(
        private Eddington $eddington,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        Eddington $eddington,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            eddington: $eddington,
            unitSystem: $unitSystem,
            translator: $translator,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        $longestDistanceInADay = $this->eddington->getLongestDistanceInADay();
        /** @var non-empty-array<mixed> $timesCompletedData */
        $timesCompletedData = $this->eddington->getTimesCompletedData();
        $eddingtonNumber = $this->eddington->getNumber();

        $yAxisMaxValue = max(ceil(max($timesCompletedData) / 30) * 30, $longestDistanceInADay);
        $yAxisInterval = ceil(($yAxisMaxValue / 5) / 30) * 30;

        $timesCompletedDataForChart = $timesCompletedData;
        $timesCompletedDataForChart[$eddingtonNumber] = [
            'value' => $timesCompletedData[$eddingtonNumber],
            'itemStyle' => [
                'color' => 'rgba(227, 73, 2, 0.8)',
            ],
        ];

        $daysNeededForFutureNumbers = $this->eddington->getDaysToCompleteForFutureNumbers();
        $daysNeededDataForChart = [];
        for ($distance = 1; $distance <= $longestDistanceInADay; ++$distance) {
            $daysNeededDataForChart[] = $daysNeededForFutureNumbers[$distance] ?? null;
        }
        $unitDistance = $this->unitSystem->distanceSymbol();

        return [
            'backgroundColor' => null,
            'animation' => true,
            'grid' => [
                'left' => '2px',
                'right' => '10px',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
            ],
            'legend' => [
                'show' => true,
                'selectedMode' => false,
                'data' => [
                    $this->translator->trans('Times completed'),
                    $this->translator->trans('Eddington'),
                ],
            ],
            'xAxis' => [
                'data' => array_map(fn (int $distance): string => $distance.$unitDistance, range(1, $longestDistanceInADay)),
                'type' => 'category',
                'axisTick' => [
                    'alignWithLabel' => true,
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'splitLine' => [
                        'show' => true,
                    ],
                    'max' => $yAxisMaxValue,
                    'interval' => $yAxisInterval,
                ],
                [
                    'type' => 'value',
                    'splitLine' => [
                        'show' => false,
                    ],
                    'max' => $yAxisMaxValue,
                    'interval' => $yAxisInterval,
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'start' => 0,
                    'end' => 100,
                    'brushSelect' => false,
                    'zoomLock' => false,
                    'zoomOnMouseWheel' => false,
                ],
            ],
            'series' => [
                [
                    'name' => $this->translator->trans('Times completed'),
                    'yAxisIndex' => 0,
                    'type' => 'bar',
                    'label' => [
                        'show' => false,
                    ],
                    'showBackground' => false,
                    'itemStyle' => [
                        'color' => 'rgba(227, 73, 2, 0.3)',
                    ],
                    'markPoint' => [
                        'symbol' => 'pin',
                        'symbolOffset' => [
                            0,
                            -5,
                        ],
                        'itemStyle' => [
                            'color' => 'rgba(227, 73, 2, 0.8)',
                        ],
                        'data' => [
                            [
                                'value' => $eddingtonNumber,
                                'coord' => [
                                    $eddingtonNumber - 1,
                                    $timesCompletedData[$eddingtonNumber] - 1,
                                ],
                            ],
                        ],
                    ],
                    'data' => array_values($timesCompletedDataForChart),
                ],
                [
                    'name' => $this->translator->trans('Eddington'),
                    'yAxisIndex' => 1,
                    'zlevel' => 1,
                    'type' => 'line',
                    'smooth' => false,
                    'showSymbol' => false,
                    'label' => [
                        'show' => false,
                    ],
                    'showBackground' => false,
                    'itemStyle' => [
                        'color' => '#E34902',
                    ],
                    'data' => range(1, $longestDistanceInADay),
                ],
                [
                    'name' => $this->translator->trans('Days needed'),
                    'yAxisIndex' => 0,
                    'type' => 'line',
                    'showSymbol' => false,
                    'lineStyle' => [
                        'opacity' => 0,
                    ],
                    'itemStyle' => [
                        'color' => 'transparent',
                    ],
                    'data' => $daysNeededDataForChart,
                ],
            ],
        ];
    }
}
