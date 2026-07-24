<?php

namespace App\Tests\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use PHPUnit\Framework\TestCase;

class MeterTest extends TestCase
{
    public function testToUnitSystem(): void
    {
        $this->assertEquals(
            Meter::zero(),
            Meter::zero()->toUnitSystem(UnitSystem::METRIC),
        );

        $this->assertEquals(
            Foot::zero(),
            Meter::zero()->toUnitSystem(UnitSystem::IMPERIAL),
        );
    }
}
