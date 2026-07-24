<?php

namespace App\Tests\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Temperature\Fahrenheit;
use PHPUnit\Framework\TestCase;

class CelsiusTest extends TestCase
{
    public function testToImperial(): void
    {
        $this->assertEquals(
            Fahrenheit::from(32),
            Celsius::zero()->toImperial()
        );
    }
}
