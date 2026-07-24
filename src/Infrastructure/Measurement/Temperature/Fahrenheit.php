<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Fahrenheit implements Unit, Imperial
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return '°F';
    }

    public function toMetric(): Unit
    {
        return Celsius::from(round(5 / 9 * ($this->value - 32), 2));
    }
}
