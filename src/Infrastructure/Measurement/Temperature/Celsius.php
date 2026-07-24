<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Celsius implements Unit, Metric
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return '°C';
    }

    public function toImperial(): Unit
    {
        return Fahrenheit::from(round(($this->value * (9 / 5)) + 32, 2));
    }
}
