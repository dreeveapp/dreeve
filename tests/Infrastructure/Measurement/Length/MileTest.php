<?php

namespace App\Tests\Infrastructure\Measurement\Length;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Mile;
use PHPUnit\Framework\TestCase;

class MileTest extends TestCase
{
    public function testToMetric(): void
    {
        $this->assertEquals(
            Kilometer::zero(),
            Mile::zero()->toMetric(),
        );
    }
}
