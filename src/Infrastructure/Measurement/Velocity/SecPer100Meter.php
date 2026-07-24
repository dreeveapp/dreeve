<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class SecPer100Meter implements Pace
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'sec/100m';
    }

    public function toUnitSystem(UnitSystem $unitSystem): self
    {
        return $this;
    }
}
