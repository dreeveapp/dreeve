<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Length\NauticalMile;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class Knot implements Unit, Velocity
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'kn';
    }

    public function toKmPerHour(): KmPerHour
    {
        return KmPerHour::from($this->value * NauticalMile::FACTOR_TO_KM);
    }

    public function toUnitSystem(UnitSystem $unitSystem): self
    {
        return $this;
    }
}
