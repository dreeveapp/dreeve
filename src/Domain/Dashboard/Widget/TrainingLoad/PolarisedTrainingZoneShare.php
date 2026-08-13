<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

final readonly class PolarisedTrainingZoneShare
{
    private function __construct(
        private PolarisedTrainingZone $zone,
        private float $percentage,
        private ZoneDistributionTrend $trend,
    ) {
    }

    public static function create(
        PolarisedTrainingZone $zone,
        float $percentage,
        ZoneDistributionTrend $trend,
    ): self {
        return new self(
            zone: $zone,
            percentage: $percentage,
            trend: $trend,
        );
    }

    public function getZone(): PolarisedTrainingZone
    {
        return $this->zone;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getTrend(): ZoneDistributionTrend
    {
        return $this->trend;
    }
}
