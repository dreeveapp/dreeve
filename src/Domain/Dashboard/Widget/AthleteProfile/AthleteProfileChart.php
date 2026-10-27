<?php

namespace App\Domain\Dashboard\Widget\AthleteProfile;

use App\Infrastructure\ECharts\EChartsCallback;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AthleteProfileChart
{
    private function __construct(
        /** @var array<int, float[]> */
        private array $chartData,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<int, float[]> $chartData
     */
    public static function create(
        array $chartData,
        TranslatorInterface $translator,
    ): self {
        return new self(
            chartData: $chartData,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $chartData = [];

        foreach ($this->chartData as $lastXDays => $data) {
            $chartData[] = [
                'value' => $data,
                'name' => $this->translator->trans('last {numberOfDays} days', ['{numberOfDays}' => $lastXDays]),
            ];
        }

        return [
            'backgroundColor' => null,
            'animation' => true,
            'grid' => [
                'left' => '5px',
                'right' => '5px',
                'bottom' => '5px',
                'containLabel' => true,
            ],
            'legend' => [
                'show' => true,
                'orient' => 'vertical',
                'right' => 0,
            ],
            'tooltip' => [
                'show' => true,
                'valueFormatter' => EChartsCallback::formatPercentage(),
            ],
            'radar' => [
                'indicator' => [
                    ['name' => $this->translator->trans('Volume'), 'max' => 100],
                    ['name' => $this->translator->trans('Consistency'), 'max' => 100],
                    ['name' => $this->translator->trans('Intensity'), 'max' => 100],
                    ['name' => $this->translator->trans('Duration'), 'max' => 100],
                    ['name' => $this->translator->trans('Density'), 'max' => 100],
                    ['name' => $this->translator->trans('Sport variety'), 'max' => 100],
                ],
            ],
            'series' => [
                [
                    'type' => 'radar',
                    'symbolSize' => 5,
                    'lineStyle' => [
                        'width' => 1,
                        'opacity' => 1,
                    ],
                    'areaStyle' => [
                        'opacity' => 0.1,
                    ],
                    'data' => $chartData,
                ],
            ],
        ];
    }
}
