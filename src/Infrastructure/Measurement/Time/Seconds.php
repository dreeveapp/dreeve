<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Time;

use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Seconds implements Unit
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 's';
    }

    public function toHour(): Hour
    {
        return Hour::from($this->value / 3600);
    }

    public function toMinute(): Minute
    {
        return Minute::from($this->value / 60);
    }
}
