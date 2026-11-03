<?php

declare(strict_types=1);

namespace App\Tests\Domain\Zwift;

use App\Domain\Zwift\ZwiftRacingScore;
use PHPUnit\Framework\TestCase;

class ZwiftRacingScoreTest extends TestCase
{
    public function testRacingScore(): void
    {
        $this->assertEquals(0, ZwiftRacingScore::fromInt(0)->getValue());
        $this->assertEquals(1000, ZwiftRacingScore::fromInt(1000)->getValue());
    }

    public function testItShouldThrowWhenInvalidRacingScore(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('ZwiftRacingScore must be a number between 0 and 1000'));

        ZwiftRacingScore::fromInt(1001);
    }
}
