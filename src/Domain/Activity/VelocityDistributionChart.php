<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\Knot;
use App\Infrastructure\Measurement\Velocity\Pace;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Time\Format\ProvideTimeFormats;

final readonly class VelocityDistributionChart
{
    use ProvideTimeFormats;
    use ProvideDistributionChart;

    private function __construct(
        /** @var array<int, int> */
        private array $velocityData,
        private KmPerHour $averageSpeed,
        private SportType $sportType,
        private UnitSystem $unitSystem,
    ) {
    }

    /**
     * @param array<int, int> $velocityData
     */
    public static function create(
        array $velocityData,
        KmPerHour $averageSpeed,
        SportType $sportType,
        UnitSystem $unitSystem,
    ): self {
        return new self(
            velocityData: $velocityData,
            averageSpeed: $averageSpeed,
            sportType: $sportType,
            unitSystem: $unitSystem,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(): ?array
    {
        // Filter out any possible flukes. Data points with less than 2 occurrences are filtered out.
        if (!$velocityData = array_filter($this->velocityData, fn (int $distribution): bool => $distribution > 2)) {
            return null;
        }
        /** @var non-empty-array<int, int> $velocityData */
        $velocities = array_keys($velocityData);
        $minVelocity = (int) floor(min($velocities) / 10) * 10;
        $maxVelocity = (int) ceil(max($velocities) / 10) * 10;

        [$step, $xAxisValues, $data] = $this->distributeOverSteps($velocityData, $minVelocity, $maxVelocity);
        $velocityUnitPreference = $this->sportType->getVelocityDisplayPreference();
        $yAxisMax = max($data) * 1.2;

        $steppedTarget = match (true) {
            $velocityUnitPreference instanceof SecPer100Meter => $this->averageSpeed->toMetersPerSecond()->toSecPerKm()->toSecPer100Meter()->toFloat(),
            $velocityUnitPreference instanceof SecPerKm => $this->averageSpeed->toMetersPerSecond()->toSecPerKm()->toUnitSystem($this->unitSystem)->toFloat(),
            $velocityUnitPreference instanceof Knot => $this->averageSpeed->toKnots()->toFloat(),
            default => $this->averageSpeed->toUnitSystem($this->unitSystem)->toFloat(),
        };

        $xAxisValueAverageVelocity = array_search($this->findClosestSteppedValue(
            min: $minVelocity,
            max: $maxVelocity,
            step: $step,
            target: $steppedTarget
        ), $xAxisValues);

        $convertedAverageVelocity = match (true) {
            $velocityUnitPreference instanceof SecPerKm => $this->formatDurationAsClock((int) round($steppedTarget)),
            default => round($steppedTarget, 1),
        };

        if ($velocityUnitPreference instanceof Pace) {
            $xAxisValues = array_map(
                $this->formatDurationAsClock(...),
                $xAxisValues,
            );
        }

        return $this->buildDistributionChart(
            xAxisValues: $xAxisValues,
            data: $data,
            yAxisMax: $yAxisMax,
            averageValue: $convertedAverageVelocity,
            averageXAxisIndex: $xAxisValueAverageVelocity,
            markPointSymbol: ['symbolSize' => 45, 'symbol' => 'roundRect'],
            markPointHeight: 0.9,
        );
    }
}
