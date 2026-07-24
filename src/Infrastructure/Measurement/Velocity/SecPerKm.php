<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class SecPerKm implements Pace, Metric
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'sec/km';
    }

    public function toSecPerMile(): SecPerMile
    {
        return SecPerMile::from($this->value * Mile::FACTOR_TO_KM);
    }

    public function toSecPer100Meter(): SecPer100Meter
    {
        return SecPer100Meter::from($this->value / 10);
    }

    public function toMetersPerSecond(): MetersPerSecond
    {
        if (0.0 === $this->value) {
            return MetersPerSecond::from(0);
        }

        return MetersPerSecond::from(round((1 / $this->value) * 1000, 3));
    }

    public function toUnitSystem(UnitSystem $unitSystem): SecPerKm|SecPerMile
    {
        if (UnitSystem::METRIC === $unitSystem) {
            return $this;
        }

        return $this->toSecPerMile();
    }

    public function toImperial(): Unit
    {
        return $this->toSecPerMile();
    }
}
