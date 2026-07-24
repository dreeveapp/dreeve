<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Imperial;
use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Mile implements ConvertableToMeter, Imperial
{
    use ProvideMeasurementUnit;

    public const float FACTOR_TO_KM = 1.60934;

    public function getSymbol(): string
    {
        return 'mi';
    }

    public function toKilometer(): Kilometer
    {
        return Kilometer::from($this->value * self::FACTOR_TO_KM);
    }

    public function toMetric(): Unit
    {
        return $this->toKilometer();
    }

    public function toMeter(): Meter
    {
        return $this->toKilometer()->toMeter();
    }
}
