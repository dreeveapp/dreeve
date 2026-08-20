<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class Celsius implements Unit, Metric
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return '°C';
    }

    public function toFahrenheit(): Fahrenheit
    {
        return Fahrenheit::from(round(($this->value * (9 / 5)) + 32, 2));
    }

    public function toUnitSystem(UnitSystem $unitSystem): Celsius|Fahrenheit
    {
        if (UnitSystem::METRIC === $unitSystem) {
            return $this;
        }

        return $this->toFahrenheit();
    }

    public function toImperial(): Unit
    {
        return $this->toFahrenheit();
    }
}
