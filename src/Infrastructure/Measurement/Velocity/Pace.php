<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;

interface Pace extends Unit, Velocity
{
    public function toUnitSystem(UnitSystem $unitSystem): self;
}
