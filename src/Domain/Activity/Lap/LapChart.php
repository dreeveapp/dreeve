<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ECharts\EChartsCallback;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LapChart
{
    private function __construct(
        private ActivityLaps $laps,
        private ActivityLapPowers $lapPowers,
        private SportType $sportType,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        ActivityLaps $laps,
        ActivityLapPowers $lapPowers,
        SportType $sportType,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
    ): self {
        return new self(
            laps: $laps,
            lapPowers: $lapPowers,
            sportType: $sportType,
            unitSystem: $unitSystem,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(): ?array
    {
        if ($this->laps->isEmpty()) {
            return null;
        }

        $speedSymbol = $this->sportType
            ->toDisplaySpeed(KmPerHour::from(1))
            ->toUnitSystem($this->unitSystem)
            ->getSymbol();

        $lapNumbers = [];
        $speedData = [];
        $heartRateData = [];
        $powerData = [];
        $hasHeartRate = false;
        $hasPower = false;

        foreach ($this->laps as $lap) {
            $lapNumbers[] = (string) $lap->getLapNumber();
            $lapPower = $this->lapPowers->forLap($lap->getLapId());
            $tooltip = $this->tooltipFor($lap, $lapPower);

            $speedData[] = [
                'value' => round($lap->getAverageSpeedInKmPerHour()->toUnitSystem($this->unitSystem)->toFloat(), 1),
                'tooltip' => $tooltip,
            ];

            $heartRate = $lap->getAverageHeartRate();
            if (null !== $heartRate) {
                $hasHeartRate = true;
            }
            $heartRateData[] = [
                'value' => $heartRate,
                'tooltip' => $tooltip,
            ];

            $averageWatts = $lapPower?->getAverageWatts();
            if (null !== $averageWatts) {
                $hasPower = true;
            }
            $powerData[] = [
                'value' => $averageWatts,
                'tooltip' => $tooltip,
            ];
        }

        $legendData = [$this->translator->trans('Speed')];
        if ($hasHeartRate) {
            $legendData[] = $this->translator->trans('Heart rate');
        }
        if ($hasPower) {
            $legendData[] = $this->translator->trans('Power');
        }

        $series = [
            [
                'name' => $this->translator->trans('Speed'),
                'type' => 'bar',
                'yAxisIndex' => 0,
                'barMaxWidth' => 30,
                'itemStyle' => ['borderRadius' => [4, 4, 0, 0]],
                'data' => $speedData,
            ],
        ];
        if ($hasHeartRate) {
            $series[] = [
                'name' => $this->translator->trans('Heart rate'),
                'type' => 'line',
                'yAxisIndex' => 1,
                'smooth' => true,
                'symbol' => 'circle',
                'symbolSize' => 6,
                'data' => $heartRateData,
            ];
        }
        if ($hasPower) {
            $series[] = [
                'name' => $this->translator->trans('Power'),
                'type' => 'line',
                'yAxisIndex' => 2,
                'smooth' => true,
                'symbol' => 'circle',
                'symbolSize' => 6,
                'data' => $powerData,
            ];
        }

        $hasLegend = $hasHeartRate || $hasPower;

        return [
            'backgroundColor' => null,
            'animation' => true,
            'grid' => [
                'left' => '1%',
                'right' => '4%',
                'bottom' => $hasLegend ? '34px' : '10px',
                'top' => '10px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => EChartsCallback::formatDataTooltip(),
            ],
            'legend' => [
                'show' => $hasLegend,
                'bottom' => 0,
                'data' => $legendData,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $lapNumbers,
                'axisTick' => ['show' => false],
                'axisLabel' => ['interval' => 'auto'],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => $speedSymbol,
                    'min' => 0,
                    'axisLabel' => ['formatter' => EChartsCallback::toInteger()],
                ],
                [
                    'type' => 'value',
                    'name' => 'bpm',
                    'show' => $hasHeartRate,
                    'min' => 0,
                    'splitLine' => ['show' => false],
                ],
                [
                    'type' => 'value',
                    'name' => 'W',
                    'show' => $hasPower,
                    'min' => 0,
                    'splitLine' => ['show' => false],
                ],
            ],
            'series' => $series,
        ];
    }

    private function tooltipFor(ActivityLap $lap, ?ActivityLapPower $lapPower): string
    {
        $distance = $lap->getDistanceInKilometer()->toUnitSystem($this->unitSystem);

        $parts = [
            sprintf('%s %s', number_format($distance->toFloat(), $this->sportType->getDistancePrecision(), '.', ' '), $distance->getSymbol()),
            $lap->getMovingTimeFormatted(),
        ];

        if (null !== $averageHeartRate = $lap->getAverageHeartRate()) {
            $parts[] = sprintf('%d bpm', $averageHeartRate);
        }

        if (null !== $averageWatts = $lapPower?->getAverageWatts()) {
            $parts[] = sprintf('%d W', $averageWatts);
        }

        return implode(' · ', $parts);
    }
}
