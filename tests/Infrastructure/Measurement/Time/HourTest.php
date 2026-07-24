<?php

namespace App\Tests\Infrastructure\Measurement\Time;

use App\Infrastructure\Measurement\Time\Hour;
use PHPUnit\Framework\TestCase;

class HourTest extends TestCase
{
    public function testGetSymbol(): void
    {
        $this->assertEquals(
            'h',
            Hour::zero()->getSymbol()
        );
    }
}
