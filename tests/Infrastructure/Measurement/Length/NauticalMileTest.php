<?php

namespace App\Tests\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Length\NauticalMile;
use PHPUnit\Framework\TestCase;

class NauticalMileTest extends TestCase
{
    public function testToKilometer(): void
    {
        $this->assertEqualsWithDelta(
            18.52,
            NauticalMile::from(10)->toKilometer()->toFloat(),
            0.0001,
        );
    }

    public function testToMeter(): void
    {
        $this->assertEqualsWithDelta(
            18520,
            NauticalMile::from(10)->toMeter()->toFloat(),
            0.0001,
        );
    }
}
