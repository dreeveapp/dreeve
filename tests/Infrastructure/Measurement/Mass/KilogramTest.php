<?php

namespace App\Tests\Infrastructure\Measurement\Mass;

use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\Mass\Pound;
use PHPUnit\Framework\TestCase;

class KilogramTest extends TestCase
{
    public function testToImperial(): void
    {
        $this->assertEquals(
            Pound::zero(),
            Kilogram::zero()->toImperial(),
        );
    }
}
