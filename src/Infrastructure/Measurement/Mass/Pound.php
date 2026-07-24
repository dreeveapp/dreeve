<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Pound implements Weight, Imperial
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'lb';
    }

    public function toMetric(): Unit
    {
        return $this->toKilogram();
    }

    public function toKilogram(): Kilogram
    {
        return Kilogram::from($this->value * 0.45359237);
    }
}
