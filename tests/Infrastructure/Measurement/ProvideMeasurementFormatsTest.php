<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Measurement;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\Velocity\MilesPerHour;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProvideMeasurementFormatsTest extends TestCase
{
    use ProvideMeasurementFormats;

    #[DataProvider(methodName: 'provideNumbers')]
    public function testFormatNumber(?float $number, int $precision, string $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            $this->formatNumber($number, $precision)
        );
    }

    #[DataProvider(methodName: 'provideUnits')]
    public function testFormatUnitWithSymbol(Unit $unit, int $precision, string $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            $this->formatUnitWithSymbol($unit, $precision)
        );
    }

    public static function provideNumbers(): array
    {
        return [
            [null, 1, '0'],
            [12.44, 1, '12.4'],
            [12.45, 2, '12.45'],
            [99.99, 1, '100.0'],
            [100.4, 1, '100'],
            [1234.56, 1, "1\u{00A0}235"],
        ];
    }

    public static function provideUnits(): array
    {
        return [
            [Kilometer::from(12.44), 1, '12.4 km'],
            [Meter::from(350), 0, '350 m'],
            [MilesPerHour::from(17.72), 1, '17.7 mph'],
        ];
    }
}
