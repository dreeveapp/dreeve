<?php

namespace App\Tests\Domain\Milestone\FunComparison;

use App\Domain\Milestone\FunComparison\MovingTimeFunComparison;
use App\Infrastructure\Measurement\Time\Hour;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class MovingTimeFunComparisonTest extends TestCase
{
    #[DataProvider(methodName: 'resolveProvider')]
    public function testResolve(float $hours, MovingTimeFunComparison $expected): void
    {
        $this->assertEquals($expected, MovingTimeFunComparison::resolve(Hour::from($hours)));
    }

    public function testTransReturnsNonEmptyStringForAllCases(): void
    {
        $translator = new IdentityTranslator();

        foreach (MovingTimeFunComparison::cases() as $case) {
            $this->assertNotEmpty($case->trans($translator));
        }
    }

    public function testResolveReturnsNullBelowMinimum(): void
    {
        $this->assertNull(MovingTimeFunComparison::resolve(Hour::from(1)));
        $this->assertNull(MovingTimeFunComparison::resolve(Hour::from(7)));
    }

    public static function resolveProvider(): \Generator
    {
        yield 'full day' => [24, MovingTimeFunComparison::FULL_DAY];
        yield 'two days' => [48, MovingTimeFunComparison::TWO_FULL_DAYS];
        yield 'four days' => [100, MovingTimeFunComparison::FOUR_DAYS];
        yield 'full week' => [168, MovingTimeFunComparison::FULL_WEEK];
        yield 'ten days' => [250, MovingTimeFunComparison::TEN_DAYS];
        yield 'three weeks' => [500, MovingTimeFunComparison::THREE_WEEKS];
        yield 'full month' => [750, MovingTimeFunComparison::FULL_MONTH];
        yield '41 days' => [1_000, MovingTimeFunComparison::FORTY_ONE_DAYS];
        yield 'two months' => [1_500, MovingTimeFunComparison::TWO_MONTHS];
        yield 'nearly three months' => [2_000, MovingTimeFunComparison::NEARLY_THREE_MONTHS];
        yield 'three and a half months' => [2_500, MovingTimeFunComparison::THREE_AND_HALF_MONTHS];
        yield 'four months' => [3_000, MovingTimeFunComparison::FOUR_MONTHS];
        yield 'five and a half' => [4_000, MovingTimeFunComparison::FIVE_AND_HALF_MONTHS];
        yield 'seven months' => [5_000, MovingTimeFunComparison::SEVEN_MONTHS];
        yield 'ten months' => [7_500, MovingTimeFunComparison::TEN_MONTHS];
        yield 'full year' => [10_000, MovingTimeFunComparison::FULL_YEAR];
    }
}
