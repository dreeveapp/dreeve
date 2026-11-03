<?php

namespace App\Tests\Domain\Athlete\MaxHeartRate;

use App\Domain\Athlete\InvalidHeartRateFormula;
use App\Domain\Athlete\MaxHeartRate\Arena;
use App\Domain\Athlete\MaxHeartRate\Astrand;
use App\Domain\Athlete\MaxHeartRate\DateRangeBased;
use App\Domain\Athlete\MaxHeartRate\Fox;
use App\Domain\Athlete\MaxHeartRate\Gellish;
use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormula;
use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormulas;
use App\Domain\Athlete\MaxHeartRate\Nes;
use App\Domain\Athlete\MaxHeartRate\Tanaka;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaxHeartRateFormulasTest extends TestCase
{
    #[DataProvider('provideDetermineFormulaData')]
    public function testDetermineFormula(MaxHeartRateFormula $expectedFormula, string|array $formula): void
    {
        $this->assertEquals(
            $expectedFormula,
            new MaxHeartRateFormulas()->determineFormula($formula)
        );
    }

    public function testItShouldThrowWhenMaxHeartRateFormulaIsEmpty(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('MAX_HEART_RATE_FORMULA cannot be empty'));
        new MaxHeartRateFormulas()->determineFormula(' ');
    }

    public function testItShouldThrowWhenMaxHeartRateFormulaIsNonExistent(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid MAX_HEART_RATE_FORMULA "invalid" detected'));
        new MaxHeartRateFormulas()->determineFormula('invalid');
    }

    public function testItShouldThrowWhenMaxHeartRateFormulaJsonContainsInvalidDates(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid date "lol" set in MAX_HEART_RATE_FORMULA'));
        new MaxHeartRateFormulas()->determineFormula(['lol' => 200]);
    }

    public function testItShouldThrowWhenMaxHeartRateFormulaJsonIsEmpty(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('MAX_HEART_RATE_FORMULA date range cannot be empty'));
        new MaxHeartRateFormulas()->determineFormula([]);
    }

    public static function provideDetermineFormulaData(): array
    {
        return [
            [new Arena(), 'arena'],
            [new Astrand(), 'astrand'],
            [
                DateRangeBased::empty()->addRange(
                    on: SerializableDateTime::fromString('2025-01-01'),
                    maxHeartRate: 100
                ),
                ['2025-01-01' => 100],
            ],
            [new Fox(), 'fox'],
            [new Gellish(), 'gellish'],
            [new Nes(), 'nes'],
            [new Tanaka(), 'tanaka'],
        ];
    }
}
