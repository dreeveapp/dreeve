<?php

namespace App\Tests\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\Mass\Pound;
use PHPUnit\Framework\TestCase;

class PoundTest extends TestCase
{
    public function testToMetric(): void
    {
        $this->assertEquals(
            Kilogram::zero(),
            Pound::zero()->toMetric(),
        );
    }
}
