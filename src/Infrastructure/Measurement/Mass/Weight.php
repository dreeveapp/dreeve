<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Unit;

interface Weight extends Unit
{
    public function toKilogram(): Kilogram;
}
