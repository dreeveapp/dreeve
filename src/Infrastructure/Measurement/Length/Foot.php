<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Foot implements Unit, Imperial
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'ft';
    }

    public function toMeter(): Meter
    {
        return Meter::from($this->value * 0.3048);
    }

    public function toMetric(): Unit
    {
        return $this->toMeter();
    }
}
