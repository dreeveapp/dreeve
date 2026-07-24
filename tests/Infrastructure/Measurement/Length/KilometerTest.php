<?php

namespace App\Tests\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\UnitSystem;
use PHPUnit\Framework\TestCase;

class KilometerTest extends TestCase
{
    public function testToUnitSystem(): void
    {
        $this->assertEquals(
            Kilometer::zero(),
            Kilometer::zero()->toUnitSystem(UnitSystem::METRIC),
        );

        $this->assertEquals(
            Mile::zero(),
            Kilometer::zero()->toUnitSystem(UnitSystem::IMPERIAL),
        );
    }

    public function testToImperial(): void
    {
        $this->assertEquals(
            Mile::zero(),
            Kilometer::zero()->toImperial(),
        );
    }
}
