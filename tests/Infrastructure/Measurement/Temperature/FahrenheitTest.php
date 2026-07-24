<?php

namespace App\Tests\Infrastructure\Measurement\Temperature;

use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Temperature\Fahrenheit;
use PHPUnit\Framework\TestCase;

class FahrenheitTest extends TestCase
{
    public function testToMetric(): void
    {
        $this->assertEquals(
            Celsius::from(-17.78),
            Fahrenheit::zero()->toMetric()
        );
    }
}
