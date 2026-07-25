<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Math;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class MathTest extends TestCase
{
    #[TestWith(data: [[1, 2, 3], 2])]
    #[TestWith(data: [[1, 2, 3, 4], 2.5])]
    public function testMedian(array $values, float $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Math::median($values)
        );
    }

    public function testMedianItShouldThrowWhenValuesAreEmpty(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Cannot calculate median of empty array.'));

        Math::median([]);
    }

    #[TestWith(data: [[], []])]
    #[TestWith(data: [[1, 2, 3, 4], [2, 2.5, 2.5, 3]])]
    public function testMovingAverage(array $values, array $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Math::movingAverage($values, 5)
        );
    }

    #[TestWith(data: [[], null])]
    #[TestWith(data: [[null, null], null])]
    #[TestWith(data: [[1.9, null, 2.9], 2.4])]
    public function testAverageFloat(array $values, ?float $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            Math::averageFloat($values)
        );
    }

    #[TestWith(data: [[], null])]
    #[TestWith(data: [[null, null], null])]
    #[TestWith(data: [[1.9, null, 2.9, 2.4], 2.9])]
    public function testMaxFloat(array $values, ?float $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            Math::maxFloat($values)
        );
    }
}
