<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class MetersPerSecond implements Unit, Velocity
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'm/s';
    }

    public function toKmPerHour(): KmPerHour
    {
        return KmPerHour::from(round($this->value * 3.6, 3));
    }

    public function toSecPerKm(): SecPerKm
    {
        if (0.0 === $this->value) {
            return SecPerKm::from(0);
        }

        return SecPerKm::from(round(1000 / $this->value, 3));
    }
}
