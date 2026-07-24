<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Metric;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Kilogram implements Weight, Metric
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'kg';
    }

    public function toPound(): Pound
    {
        return Pound::from($this->value * 2.20462);
    }

    public function toImperial(): Unit
    {
        return $this->toPound();
    }

    public function toKilogram(): Kilogram
    {
        return $this;
    }
}
