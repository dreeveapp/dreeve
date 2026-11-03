<?php

namespace App\Tests\Domain\Milestone\FunComparison;

use App\Domain\Milestone\FunComparison\StreakFunComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class StreakFunComparisonTest extends TestCase
{
    #[DataProvider('resolveProvider')]
    public function testResolve(int $days, StreakFunComparison $expected): void
    {
        $this->assertEquals($expected, StreakFunComparison::resolve($days));
    }

    public function testTransReturnsNonEmptyStringForAllCases(): void
    {
        $translator = new IdentityTranslator();

        foreach (StreakFunComparison::cases() as $case) {
            $this->assertNotEmpty($case->trans($translator));
        }
    }

    public function testResolveReturnsNullForThresholdsWithoutComparison(): void
    {
        $this->assertNull(StreakFunComparison::resolve(1));
        $this->assertNull(StreakFunComparison::resolve(6));
        $this->assertNull(StreakFunComparison::resolve(15));
        $this->assertNull(StreakFunComparison::resolve(31));
    }

    public static function resolveProvider(): \Generator
    {
        yield 'fortnight' => [14, StreakFunComparison::FORTNIGHT];
        yield '21 days habit' => [21, StreakFunComparison::TWENTY_ONE_DAYS_HABIT];
        yield 'full month' => [30, StreakFunComparison::FULL_MONTH];
        yield '45 days' => [45, StreakFunComparison::FORTY_FIVE_DAYS];
        yield 'two months' => [60, StreakFunComparison::TWO_MONTHS];
        yield 'full quarter' => [90, StreakFunComparison::FULL_QUARTER];
        yield '100 days' => [100, StreakFunComparison::HUNDRED_DAYS];
        yield 'four months' => [120, StreakFunComparison::FOUR_MONTHS];
        yield 'five months' => [150, StreakFunComparison::FIVE_MONTHS];
        yield 'half year' => [180, StreakFunComparison::HALF_YEAR];
        yield '250 days' => [250, StreakFunComparison::TWO_HUNDRED_FIFTY_DAYS];
        yield 'full year' => [365, StreakFunComparison::FULL_YEAR];
        yield '500 days' => [500, StreakFunComparison::FIVE_HUNDRED_DAYS];
        yield 'two years' => [730, StreakFunComparison::TWO_YEARS];
    }
}
