<?php

namespace App\Tests\Domain\Athlete\MaxHeartRate;

use App\Domain\Athlete\MaxHeartRate\Arena;
use App\Domain\Athlete\MaxHeartRate\Astrand;
use App\Domain\Athlete\MaxHeartRate\Fox;
use App\Domain\Athlete\MaxHeartRate\Gellish;
use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormula;
use App\Domain\Athlete\MaxHeartRate\Nes;
use App\Domain\Athlete\MaxHeartRate\Tanaka;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaxHeartRateFormulaTest extends TestCase
{
    #[DataProvider(methodName: 'provideCalculateData')]
    public function testCalculate(MaxHeartRateFormula $formula, int $age, int $expectedHeartRate): void
    {
        $this->assertEquals(
            $expectedHeartRate,
            $formula->calculate($age, SerializableDateTime::fromString('2021-01-01 13:00:00'))
        );
    }

    public static function provideCalculateData(): iterable
    {
        yield 'Arena, aged 20' => [new Arena(), 20, 195];
        yield 'Arena, aged 30' => [new Arena(), 30, 188];
        yield 'Arena, aged 35' => [new Arena(), 35, 184];
        yield 'Arena, aged 40' => [new Arena(), 40, 181];
        yield 'Arena, aged 50' => [new Arena(), 50, 173];
        yield 'Arena, aged 60' => [new Arena(), 60, 166];
        yield 'Arena, aged 70' => [new Arena(), 70, 159];
        yield 'Arena, aged 80' => [new Arena(), 80, 152];
        yield 'Arena, aged 90' => [new Arena(), 90, 145];
        yield 'Arena, aged 100' => [new Arena(), 100, 137];
        yield 'Astrand, aged 20' => [new Astrand(), 20, 200];
        yield 'Astrand, aged 30' => [new Astrand(), 30, 191];
        yield 'Astrand, aged 35' => [new Astrand(), 35, 187];
        yield 'Astrand, aged 40' => [new Astrand(), 40, 183];
        yield 'Astrand, aged 50' => [new Astrand(), 50, 175];
        yield 'Astrand, aged 60' => [new Astrand(), 60, 166];
        yield 'Astrand, aged 70' => [new Astrand(), 70, 158];
        yield 'Astrand, aged 80' => [new Astrand(), 80, 149];
        yield 'Astrand, aged 90' => [new Astrand(), 90, 141];
        yield 'Astrand, aged 100' => [new Astrand(), 100, 133];
        yield 'Fox, aged 20' => [new Fox(), 20, 200];
        yield 'Fox, aged 30' => [new Fox(), 30, 190];
        yield 'Fox, aged 35' => [new Fox(), 35, 185];
        yield 'Fox, aged 40' => [new Fox(), 40, 180];
        yield 'Fox, aged 50' => [new Fox(), 50, 170];
        yield 'Fox, aged 60' => [new Fox(), 60, 160];
        yield 'Fox, aged 70' => [new Fox(), 70, 150];
        yield 'Fox, aged 80' => [new Fox(), 80, 140];
        yield 'Fox, aged 90' => [new Fox(), 90, 130];
        yield 'Fox, aged 100' => [new Fox(), 100, 120];
        yield 'Gellish, aged 20' => [new Gellish(), 20, 193];
        yield 'Gellish, aged 30' => [new Gellish(), 30, 186];
        yield 'Gellish, aged 35' => [new Gellish(), 35, 183];
        yield 'Gellish, aged 40' => [new Gellish(), 40, 179];
        yield 'Gellish, aged 50' => [new Gellish(), 50, 172];
        yield 'Gellish, aged 60' => [new Gellish(), 60, 165];
        yield 'Gellish, aged 70' => [new Gellish(), 70, 158];
        yield 'Gellish, aged 80' => [new Gellish(), 80, 151];
        yield 'Gellish, aged 90' => [new Gellish(), 90, 144];
        yield 'Gellish, aged 100' => [new Gellish(), 100, 137];
        yield 'Nes, aged 20' => [new Nes(), 20, 198];
        yield 'Nes, aged 30' => [new Nes(), 30, 192];
        yield 'Nes, aged 35' => [new Nes(), 35, 189];
        yield 'Nes, aged 40' => [new Nes(), 40, 185];
        yield 'Nes, aged 50' => [new Nes(), 50, 179];
        yield 'Nes, aged 60' => [new Nes(), 60, 173];
        yield 'Nes, aged 70' => [new Nes(), 70, 166];
        yield 'Nes, aged 80' => [new Nes(), 80, 160];
        yield 'Nes, aged 90' => [new Nes(), 90, 153];
        yield 'Nes, aged 100' => [new Nes(), 100, 147];
        yield 'Tanaka, aged 20' => [new Tanaka(), 20, 194];
        yield 'Tanaka, aged 30' => [new Tanaka(), 30, 187];
        yield 'Tanaka, aged 35' => [new Tanaka(), 35, 184];
        yield 'Tanaka, aged 40' => [new Tanaka(), 40, 180];
        yield 'Tanaka, aged 50' => [new Tanaka(), 50, 173];
        yield 'Tanaka, aged 60' => [new Tanaka(), 60, 166];
        yield 'Tanaka, aged 70' => [new Tanaka(), 70, 159];
        yield 'Tanaka, aged 80' => [new Tanaka(), 80, 152];
        yield 'Tanaka, aged 90' => [new Tanaka(), 90, 145];
        yield 'Tanaka, aged 100' => [new Tanaka(), 100, 138];
    }
}
