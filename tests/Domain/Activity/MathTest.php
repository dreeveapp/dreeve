<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Math;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class MathTest extends TestCase
{
    /**
     * @param list<int> $values
     */
    #[TestWith([[1, 2, 3], 2])]
    #[TestWith([[1, 2, 3, 4], 2.5])]
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

    /**
     * @param list<int>       $values
     * @param list<int|float> $expectedResult
     */
    #[TestWith([[], []])]
    #[TestWith([[1, 2, 3, 4], [2, 2.5, 2.5, 3]])]
    public function testMovingAverage(array $values, array $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            Math::movingAverage($values, 5)
        );
    }

    /**
     * @param list<float|null> $values
     */
    #[TestWith([[], null])]
    #[TestWith([[null, null], null])]
    #[TestWith([[1.9, null, 2.9], 2.4])]
    public function testAverageFloat(array $values, ?float $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            Math::averageFloat($values)
        );
    }

    /**
     * @param list<float|null> $values
     */
    #[TestWith([[], null])]
    #[TestWith([[null, null], null])]
    #[TestWith([[1.9, null, 2.9, 2.4], 2.9])]
    public function testMaxFloat(array $values, ?float $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            Math::maxFloat($values)
        );
    }
}
