<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class Fahrenheit implements Unit, Imperial
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return '°F';
    }

    public function toCelsius(): Celsius
    {
        return Celsius::from(round(5 / 9 * ($this->value - 32), 2));
    }

    public function toUnitSystem(UnitSystem $unitSystem): Celsius|Fahrenheit
    {
        if (UnitSystem::IMPERIAL === $unitSystem) {
            return $this;
        }

        return $this->toCelsius();
    }

    public function toMetric(): Unit
    {
        return $this->toCelsius();
    }
}
