<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

final class TrainingMetrics
{
    /** @var array<string, int|float> */
    private array $atlValues;
    /** @var array<string, int|float> */
    private array $ctlValues;
    /** @var array<string, int|float> */
    private array $tsbValues;
    /** @var array<string, int|float|null> */
    private array $trimpValues;
    /** @var array<string, int|float|null> */
    private array $monotonyValues;
    /** @var array<string, int|float|null> */
    private array $strainValues;
    /** @var array<string, int|float> */
    private array $acRatioValues;

    private function __construct(
        /** @var array<string, int> */
        private readonly array $intensities,
    ) {
        $this->buildMetrics();
    }

    /**
     * @param array<string, int> $intensities
     */
    public static function create(array $intensities): TrainingMetrics
    {
        return new self($intensities);
    }

    private function buildMetrics(): void
    {
        $alphaATL = 1 - exp(-1 / 7);
        $alphaCTL = 1 - exp(-1 / TrainingLoadChart::ROLLING_WINDOW_TO_CALCULATE_METRICS_AGAINST);

        $altValues = $ctlValues = $tsbValues = $trimpValues = $monotonyValues = $strainValues = $acRatioValues = [];

        $delta = 0;
        foreach ($this->intensities as $intensity) {
            $trimpValues[$delta] = $intensity;

            if (0 === $delta) {
                $altValues[$delta] = $intensity;
                $ctlValues[$delta] = $intensity;
                $tsbValues[$delta] = 0;
            } else {
                $altValues[$delta] = ($intensity * $alphaATL) + ($altValues[$delta - 1] * (1 - $alphaATL));
                $ctlValues[$delta] = ($intensity * $alphaCTL) + ($ctlValues[$delta - 1] * (1 - $alphaCTL));
                $tsbValues[$delta] = $ctlValues[$delta] - $altValues[$delta];
            }

            if ($delta >= 6) { // Day 6 = first full week
                $weekLoads = array_slice($this->intensities, $delta - 6, 7);
                $sum = array_sum($weekLoads);
                $avg = $sum / 7;
                $std = $this->standardDeviation($weekLoads);

                $monotonyValues[$delta] = $std > 0 ? $avg / $std : 0;
                $strainValues[$delta] = $sum * $monotonyValues[$delta];
            } else {
                $monotonyValues[$delta] = null;
                $strainValues[$delta] = null;
            }

            $acRatioValues[$delta] = 0 == $ctlValues[$delta] ? 0 : round($altValues[$delta] / $ctlValues[$delta], 2);

            ++$delta;
        }

        $intensityKeys = array_keys($this->intensities);
        // Round numbers when all calculating is done and combine with original keys.
        $this->acRatioValues = array_combine($intensityKeys, $acRatioValues);
        $this->atlValues = array_combine($intensityKeys, array_map(fn (int|float $value): float => round($value, 1), $altValues));
        $this->ctlValues = array_combine($intensityKeys, array_map(fn (int|float $value): float => round($value, 1), $ctlValues));
        $this->tsbValues = array_combine($intensityKeys, array_map(fn (int|float $value): float => round($value, 1), $tsbValues));
        // Apply rounding/casting to the daily trimp values
        $this->trimpValues = array_combine($intensityKeys, array_map(fn (int|float|null $value): ?int => null === $value ? null : (int) round($value), $trimpValues));
        $this->strainValues = array_combine($intensityKeys, array_map(fn (int|float|null $value): ?int => null === $value ? null : (int) round($value), $strainValues));
        $this->monotonyValues = array_combine($intensityKeys, array_map(fn (int|float|null $value): ?float => null === $value ? null : round($value, 2), $monotonyValues));
    }

    /**
     * @return array<int, int|float>
     */
    public function getAtlValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->atlValues, -$numberOfDays));
    }

    public function getCurrentAtl(): ?float
    {
        if ([] === $this->atlValues) {
            return null;
        }

        return end($this->atlValues);
    }

    /**
     * @return array<int, int|float>
     */
    public function getCtlValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->ctlValues, -$numberOfDays));
    }

    public function getCurrentCtl(): ?float
    {
        if ([] === $this->ctlValues) {
            return null;
        }

        return end($this->ctlValues);
    }

    /**
     * @return array<int, int|float>
     */
    public function getTsbValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->tsbValues, -$numberOfDays));
    }

    public function getCurrentTsb(): ?TSB
    {
        if ([] === $this->tsbValues) {
            return null;
        }

        return TSB::of(end($this->tsbValues));
    }

    /**
     * @return array<int, int|float|null>
     */
    public function getTrimpValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->trimpValues, -$numberOfDays));
    }

    public function getWeeklyTrimp(): ?int
    {
        if (count($this->trimpValues) < 7) {
            return null;
        }

        $lastSevenDaysTrimp = array_slice($this->trimpValues, -7);

        return (int) array_sum($lastSevenDaysTrimp);
    }

    public function getCurrentMonotony(): ?float
    {
        if ([] === $this->monotonyValues) {
            return null;
        }

        return end($this->monotonyValues);
    }

    public function getCurrentStrain(): ?float
    {
        if ([] === $this->strainValues) {
            return null;
        }

        return end($this->strainValues);
    }

    public function getCurrentAcRatio(): ?AcRatio
    {
        if ([] === $this->acRatioValues) {
            return null;
        }

        return AcRatio::of(end($this->acRatioValues));
    }

    /**
     * @param array<string, int|float> $values
     */
    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if (0 === $count) {
            return 0.0;
        }
        $mean = array_sum($values) / $count;
        $sumSquares = 0;
        foreach ($values as $v) {
            $sumSquares += ($v - $mean) ** 2;
        }

        return sqrt($sumSquares / $count);
    }
}
