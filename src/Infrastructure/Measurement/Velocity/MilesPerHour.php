<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class MilesPerHour implements Unit, Imperial, Velocity
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'mph';
    }

    public function toKmH(): KmPerHour
    {
        return KmPerHour::from($this->value * Mile::FACTOR_TO_KM);
    }

    public function toMetric(): Unit
    {
        return $this->toKmH();
    }
}
