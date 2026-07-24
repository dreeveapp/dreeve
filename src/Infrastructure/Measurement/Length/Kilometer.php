<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class Kilometer implements ConvertableToMeter, Metric
{
    use ProvideMeasurementUnit;

    public const float FACTOR_TO_MILES = 0.621371;

    public function getSymbol(): string
    {
        return 'km';
    }

    public function toMiles(): Mile
    {
        return Mile::from($this->value * self::FACTOR_TO_MILES);
    }

    public function toUnitSystem(UnitSystem $unitSystem): Kilometer|Mile
    {
        if (UnitSystem::METRIC === $unitSystem) {
            return $this;
        }

        return $this->toMiles();
    }

    public function toImperial(): Unit
    {
        return $this->toMiles();
    }

    public function toMeter(): Meter
    {
        return Meter::from($this->value * 1000);
    }
}
