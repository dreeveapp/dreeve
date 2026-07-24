<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class SecPerMile implements Pace, Imperial
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'sec/mi';
    }

    public function toSecPerKm(): SecPerKm
    {
        return SecPerKm::from($this->value * Kilometer::FACTOR_TO_MILES);
    }

    public function toUnitSystem(UnitSystem $unitSystem): Pace
    {
        if (UnitSystem::IMPERIAL === $unitSystem) {
            return $this;
        }

        return $this->toSecPerKm();
    }

    public function toMetric(): Unit
    {
        return $this->toSecPerKm();
    }
}
