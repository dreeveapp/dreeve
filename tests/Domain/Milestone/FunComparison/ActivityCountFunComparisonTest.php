<?php

namespace App\Tests\Domain\Milestone\FunComparison;

use App\Domain\Milestone\FunComparison\ActivityCountFunComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class ActivityCountFunComparisonTest extends TestCase
{
    public function testTransReturnsNonEmptyStringForAllCases(): void
    {
        $translator = new IdentityTranslator();

        foreach (ActivityCountFunComparison::cases() as $case) {
            $this->assertNotEmpty($case->trans($translator));
        }
    }

    public function testResolveReturnsNullBelowMinimum(): void
    {
        $this->assertNull(ActivityCountFunComparison::resolve(9));
    }

    #[DataProvider('resolveProvider')]
    public function testResolve(int $count, ActivityCountFunComparison $expected): void
    {
        $this->assertEquals($expected, ActivityCountFunComparison::resolve($count));
    }

    /**
     * @return \Generator<string, array{int, ActivityCountFunComparison}>
     */
    public static function resolveProvider(): \Generator
    {
        yield '10' => [10, ActivityCountFunComparison::ONE_PER_WEEK_TWO_MONTHS];
        yield '25' => [25, ActivityCountFunComparison::ONE_PER_WEEK_HALF_YEAR];
        yield '50' => [50, ActivityCountFunComparison::WEEKLY_FOR_YEAR];
        yield '100' => [100, ActivityCountFunComparison::TWICE_WEEKLY_FOR_YEAR];
        yield '250' => [250, ActivityCountFunComparison::FIVE_WEEKLY_FOR_YEAR];
        yield '500' => [500, ActivityCountFunComparison::TWICE_WEEKLY_FOR_FIVE_YEARS];
        yield '750' => [750, ActivityCountFunComparison::THREE_WEEKLY_FOR_FIVE_YEARS];
        yield '1000' => [1_000, ActivityCountFunComparison::THREE_WEEKLY_FOR_SEVEN_YEARS];
        yield '1500' => [1_500, ActivityCountFunComparison::FOUR_WEEKLY_FOR_SEVEN_YEARS];
        yield '2000' => [2_000, ActivityCountFunComparison::FIVE_WEEKLY_FOR_SEVEN_YEARS];
        yield '2500' => [2_500, ActivityCountFunComparison::DAILY_FOR_SEVEN_YEARS];
        yield '3000' => [3_000, ActivityCountFunComparison::DAILY_FOR_EIGHT_YEARS];
        yield '4000' => [4_000, ActivityCountFunComparison::DAILY_FOR_ELEVEN_YEARS];
        yield '5000' => [5_000, ActivityCountFunComparison::DAILY_FOR_FOURTEEN_YEARS];
        yield '7500' => [7_500, ActivityCountFunComparison::DAILY_FOR_TWENTY_YEARS];
        yield '10000' => [10_000, ActivityCountFunComparison::DAILY_FOR_TWENTYSEVEN_YEARS];
    }
}
