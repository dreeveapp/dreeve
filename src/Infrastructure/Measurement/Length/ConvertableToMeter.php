<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Unit;

interface ConvertableToMeter extends Unit
{
    public function toMeter(): Meter;
}
