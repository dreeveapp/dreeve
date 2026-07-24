<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Time;

use App\Infrastructure\Measurement\ProvideMeasurementUnit;
use App\Infrastructure\Measurement\Unit;

final readonly class Minute implements Unit
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return 'min';
    }
}
