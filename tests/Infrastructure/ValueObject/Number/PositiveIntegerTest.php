<?php

namespace App\Tests\Infrastructure\ValueObject\Number;

use App\Infrastructure\ValueObject\Number\PositiveInteger;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PositiveIntegerTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([33])]
    public function testFromInt(int $value): void
    {
        $this->assertEquals($value, PositiveInteger::fromInt($value)->getValue());
    }

    #[TestWith([0, 0])]
    #[TestWith([33, 33])]
    #[TestWith([null, null])]
    public function testFromOptionalInt(?int $value, ?int $expectedValue): void
    {
        $this->assertEquals($expectedValue, PositiveInteger::fromOptionalInt($value)?->getValue());
    }

    public function testItShouldThrowWhenNegative(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Value must be a positive integer, got: -10'));
        PositiveInteger::fromInt(-10);
    }

    #[TestWith(['0', 0])]
    #[TestWith(['33', 33])]
    #[TestWith([null, null])]
    #[TestWith(['', null])]
    public function testFromOptionalString(?string $value, ?int $expectedValue): void
    {
        $this->assertEquals($expectedValue, PositiveInteger::fromOptionalString($value)?->getValue());
    }

    public function testFromOptionalStringWhenNotNumeric(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Value must be an integer, got "lol"'));
        PositiveInteger::fromOptionalString('lol');
    }
}
