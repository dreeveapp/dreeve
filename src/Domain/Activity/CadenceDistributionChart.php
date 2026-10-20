<?php

declare(strict_types=1);

namespace App\Domain\Activity;

final readonly class CadenceDistributionChart
{
    use ProvideDistributionChart;

    private function __construct(
        /** @var array<int, int> */
        private array $cadenceData,
        private int $averageCadence,
        private int $labelMultiplier,
    ) {
    }

    /**
     * @param array<int, int> $cadenceData
     */
    public static function create(
        array $cadenceData,
        int $averageCadence,
        ActivityType $activityType,
    ): self {
        return new self(
            cadenceData: $cadenceData,
            averageCadence: $averageCadence,
            labelMultiplier: in_array($activityType, [ActivityType::RUN, ActivityType::WALK]) ? 2 : 1,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(): ?array
    {
        if (!$cadenceData = array_filter($this->cadenceData, fn (int $distribution): bool => $distribution > 2)) {
            return null;
        }
        /** @var non-empty-array<int, int> $cadenceData */
        $cadences = array_keys($cadenceData);
        $minCadence = (int) floor(min($cadences) / 10) * 10;
        $maxCadence = (int) max(10, ceil(max($cadences) / 10) * 10);

        [$step, $xAxisValues, $data] = $this->distributeOverSteps($cadenceData, $minCadence, $maxCadence);
        $yAxisMax = max($data) * 1.2;
        $xAxisValueAverageCadence = array_search($this->findClosestSteppedValue(
            min: $minCadence,
            max: $maxCadence,
            step: $step,
            target: $this->averageCadence,
        ), $xAxisValues);

        return $this->buildDistributionChart(
            xAxisValues: array_map(fn (int $value): int => $value * $this->labelMultiplier, $xAxisValues),
            data: $data,
            yAxisMax: $yAxisMax,
            averageValue: $this->averageCadence * $this->labelMultiplier,
            averageXAxisIndex: $xAxisValueAverageCadence,
            markPointSymbol: ['symbolSize' => 45, 'symbol' => 'roundRect'],
            markPointHeight: 0.9,
        );
    }
}
