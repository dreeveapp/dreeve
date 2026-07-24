<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Gram implements Unit, Metric
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'gr';
    }

    public function toKilogram(): Kilogram
    {
        return Kilogram::from($this->value / 1000);
    }

    public function toImperial(): Unit
    {
        return Pound::from($this->value * 0.00220462262);
    }
}
