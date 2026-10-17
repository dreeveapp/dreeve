<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RelativeHeartRateDistributionChart
{
    private const string BELOW_ZONES_COLOR = '#7F7F7F';

    private function __construct(
        private RelativeHeartRateDistribution $relativeHeartRateDistribution,
        private HeartRateZones $heartRateZones,
        private int $athleteMaxHeartRate,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        RelativeHeartRateDistribution $relativeHeartRateDistribution,
        HeartRateZones $heartRateZones,
        int $athleteMaxHeartRate,
        TranslatorInterface $translator,
    ): self {
        return new self(
            relativeHeartRateDistribution: $relativeHeartRateDistribution,
            heartRateZones: $heartRateZones,
            athleteMaxHeartRate: $athleteMaxHeartRate,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $bins = $this->relativeHeartRateDistribution->getBins();
        $totalTimeInSeconds = array_sum($bins);
        $zoneLabels = [
            HeartRateZone::ONE => $this->translator->trans('Zone 1'),
            HeartRateZone::TWO => $this->translator->trans('Zone 2'),
            HeartRateZone::THREE => $this->translator->trans('Zone 3'),
            HeartRateZone::FOUR => $this->translator->trans('Zone 4'),
            HeartRateZone::FIVE => $this->translator->trans('Zone 5'),
        ];

        $zoneNamePerBin = [];
        foreach (array_keys($bins) as $percentage) {
            $zoneNamePerBin[$percentage] = null;
            foreach ($this->heartRateZones->getZones() as $heartRateZone) {
                if ($percentage >= $heartRateZone->getFromPercentage($this->athleteMaxHeartRate)
                    && $percentage <= $heartRateZone->getToPercentage($this->athleteMaxHeartRate)) {
                    $zoneNamePerBin[$percentage] = $heartRateZone->getName();
                    break;
                }
            }
        }

        $series = [];
        $legend = [];
        foreach ([...$this->heartRateZones->getZones(), null] as $heartRateZone) {
            $zoneName = $heartRateZone?->getName();
            $data = [];
            foreach ($bins as $percentage => $seconds) {
                if ($zoneNamePerBin[$percentage] !== $zoneName) {
                    $data[] = '-';
                    continue;
                }
                $data[] = round($seconds / $totalTimeInSeconds * 100, 2);
            }

            $name = $this->translator->trans('Below zone 1');
            if (null !== $zoneName) {
                $name = $zoneLabels[$zoneName];
                $legend[] = $name;
            }

            $series[] = [
                'name' => $name,
                'type' => 'bar',
                'stack' => 'heartRate',
                'barCategoryGap' => '18%',
                'emphasis' => [
                    'focus' => 'none',
                ],
                'itemStyle' => [
                    'color' => $heartRateZone?->getColor() ?? self::BELOW_ZONES_COLOR,
                    'borderRadius' => [2, 2, 0, 0],
                ],
                'data' => $data,
            ];
        }

        $percentages = array_keys($bins);
        $xAxisLabels = array_map(strval(...), $percentages);
        $xAxisLabels[0] = '≤'.$xAxisLabels[0];
        if (count($xAxisLabels) > 1) {
            $xAxisLabels[count($xAxisLabels) - 1] = '≥'.$xAxisLabels[count($xAxisLabels) - 1];
        }

        return [
            'backgroundColor' => null,
            'animationDuration' => 300,
            'grid' => [
                'left' => 52,
                'right' => 16,
                'top' => 40,
                'bottom' => 30,
            ],
            'legend' => [
                'top' => 0,
                'left' => 0,
                'icon' => 'rect',
                'itemWidth' => 10,
                'itemHeight' => 10,
                'data' => $legend,
            ],
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => '{a}: <strong>{c}%</strong>',
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $xAxisLabels,
                'axisTick' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'interval' => 4,
                    'fontFamily' => 'monospace',
                    'fontSize' => 11,
                    'formatter' => '{value}%',
                ],
            ],
            'yAxis' => [
                'type' => 'value',
                'axisLabel' => [
                    'fontFamily' => 'monospace',
                    'fontSize' => 11,
                    'formatter' => '{value}%',
                ],
            ],
            'series' => $series,
        ];
    }
}
