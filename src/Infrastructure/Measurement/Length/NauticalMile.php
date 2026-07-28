<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class NauticalMile implements ConvertableToMeter
{
    use ProvideMeasurementUnit;

    public const float FACTOR_TO_KM = 1.852;

    public function getSymbol(): string
    {
        return 'NM';
    }

    public function toKilometer(): Kilometer
    {
        return Kilometer::from($this->value * self::FACTOR_TO_KM);
    }

    public function toMeter(): Meter
    {
        return $this->toKilometer()->toMeter();
    }

    public function toUnitSystem(UnitSystem $unitSystem): self
    {
        return $this;
    }
}
