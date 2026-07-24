<?php

namespace App\Tests\Infrastructure\Measurement\Velocity;

use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Measurement\Velocity\SecPerMile;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class SecPerKmTest extends TestCase
{
    #[TestWith(data: [300, 3.333])]
    #[TestWith(data: [0, 0])]
    public function testToMetersPerSecond(int $valueToConvert, float $expectedResult): void
    {
        $this->assertEquals(
            MetersPerSecond::from($expectedResult),
            SecPerKm::from($valueToConvert)->toMetersPerSecond()
        );
    }

    #[TestWith(data: [300, 482.802])]
    #[TestWith(data: [0, 0])]
    public function testToImperial(int $valueToConvert, float $expectedResult): void
    {
        $this->assertEquals(
            SecPerMile::from($expectedResult),
            SecPerKm::from($valueToConvert)->toImperial()
        );
    }
}
