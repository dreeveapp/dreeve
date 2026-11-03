<?php

namespace App\Tests\Domain\Athlete\RestingHeartRate;

use App\Domain\Athlete\InvalidHeartRateFormula;
use App\Domain\Athlete\RestingHeartRate\DateRangeBased;
use App\Domain\Athlete\RestingHeartRate\Fixed;
use App\Domain\Athlete\RestingHeartRate\HeuristicAgeBased;
use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormula;
use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormulas;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RestingHeartRateFormulasTest extends TestCase
{
    /**
     * @param string|array<string, int>|int $formula
     */
    #[DataProvider('provideDetermineFormulaData')]
    public function testDetermineFormula(RestingHeartRateFormula $expectedFormula, string|array|int $formula): void
    {
        $this->assertEquals(
            $expectedFormula,
            new RestingHeartRateFormulas()->determineFormula($formula)
        );
    }

    public function testItShouldThrowWhenRestingHeartRateFormulaIsInvalid(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid RESTING_HEART_RATE_FORMULA " " detected'));
        new RestingHeartRateFormulas()->determineFormula(' ');
    }

    public function testItShouldThrowWhenRestingHeartRateFormulaIsEmpty(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('RESTING_HEART_RATE_FORMULA date range cannot be empty'));
        new RestingHeartRateFormulas()->determineFormula([]);
    }

    public function testItShouldThrowWhenMaxHeartRateFormulaJsonContainsInvalidDates(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid date "lol" set in RESTING_HEART_RATE_FORMULA'));
        new RestingHeartRateFormulas()->determineFormula(['lol' => 200]);
    }

    /**
     * @return list<array{RestingHeartRateFormula, string|array<string, int>|int}>
     */
    public static function provideDetermineFormulaData(): array
    {
        return [
            [new Fixed(205), 205],
            [new Fixed(205), '205'],
            [new HeuristicAgeBased(), 'heuristicAgeBased'],
            [
                DateRangeBased::empty()->addRange(
                    on: SerializableDateTime::fromString('2025-01-01'),
                    maxHeartRate: 100
                ),
                ['2025-01-01' => 100],
            ],
        ];
    }
}
