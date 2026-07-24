<?php

namespace App\Tests\Infrastructure\Measurement\Time;

use App\Infrastructure\Measurement\Time\Minute;
use PHPUnit\Framework\TestCase;

class MinuteTest extends TestCase
{
    public function testGetSymbol(): void
    {
        $this->assertEquals(
            'min',
            Minute::zero()->getSymbol()
        );
    }
}
