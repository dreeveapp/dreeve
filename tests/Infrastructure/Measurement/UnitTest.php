<?php

namespace App\Tests\Infrastructure\Measurement;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\Length\NauticalMile;
use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\Mass\Pound;
use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Temperature\Fahrenheit;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Minute;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\Knot;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\MilesPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Measurement\Velocity\SecPerMile;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnitTest extends TestCase
{
    #[DataProvider(methodName: 'provideConversions')]
    public function testConversions(Unit $a, Unit $b): void
    {
        $this->assertEquals(
            $a,
            $b
        );
    }

    public function testSubtract(): void
    {
        $this->assertEquals(
            Kilometer::from(10),
            Kilometer::from(220)->subtract(Kilometer::from(210))
        );
    }

    public function testSubtractItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Cannot subtract value of type "App\Infrastructure\Measurement\Length\Kilometer" with type "App\Infrastructure\Measurement\Length\Mile"'));
        Kilometer::from(220)->subtract(Mile::from(210));
    }

    public static function provideConversions(): array
    {
        return [
            [Meter::from(0.6096), Foot::from(2)->toMeter()],
            [Mile::from(1.242742), Kilometer::from(2)->toMiles()],
            [Foot::from(6.561), Meter::from(2)->toFoot()],
            [Kilometer::from(3.21868), Mile::from(2)->toKilometer()],
            [Pound::from(22.0462), Kilogram::from(10)->toPound()],
            [Kilogram::from(4.535923700000001), Pound::from(10)->toKilogram()],
            [MilesPerHour::from(6.21371), KmPerHour::from(10)->toMph()],
            [KmPerHour::from(16.0934), MilesPerHour::from(10)->toKmH()],
            [Celsius::from(-12.22), Fahrenheit::from(10)->toMetric()],
            [Fahrenheit::from(10), Celsius::from(-12.22)->toImperial()],
            [KmPerHour::from(57.6), MetersPerSecond::from(16)->toKmPerHour()],
            [SecPerKm::from(62.5), MetersPerSecond::from(16)->toSecPerKm()],
            [MetersPerSecond::from(125), SecPerKm::from(8)->toMetersPerSecond()],
            [MetersPerSecond::from(3.417), KmPerHour::from(12.3)->toMetersPerSecond()],
            [NauticalMile::from(0.5399568), Kilometer::from(1)->toNauticalMiles()],
            [Kilometer::from(1.852), NauticalMile::from(1)->toKilometer()],
            [Meter::from(1852), NauticalMile::from(1)->toMeter()],
            [Knot::from(0.5399568), KmPerHour::from(1)->toKnots()],
            [KmPerHour::from(1.852), Knot::from(1)->toKmPerHour()],
            [Kilogram::zero(), Pound::zero()->toMetric()],
            [Pound::zero(), Kilogram::zero()->toImperial()],
            [Kilometer::zero(), Mile::zero()->toMetric()],
            [Mile::zero(), Kilometer::zero()->toImperial()],
            [Kilometer::zero(), Kilometer::zero()->toUnitSystem(UnitSystem::METRIC)],
            [Mile::zero(), Kilometer::zero()->toUnitSystem(UnitSystem::IMPERIAL)],
            [Meter::zero(), Meter::zero()->toUnitSystem(UnitSystem::METRIC)],
            [Foot::zero(), Meter::zero()->toUnitSystem(UnitSystem::IMPERIAL)],
            [Fahrenheit::from(32), Celsius::zero()->toImperial()],
            [Celsius::from(-17.78), Fahrenheit::zero()->toMetric()],
            [SecPerKm::from(186.4113), SecPerMile::from(300)->toMetric()],
            [SecPerKm::from(186.4113), SecPerMile::from(300)->toSecPerKm()],
            [SecPerKm::from(0), SecPerMile::from(0)->toMetric()],
            [MetersPerSecond::from(3.333), SecPerKm::from(300)->toMetersPerSecond()],
            [MetersPerSecond::from(0), SecPerKm::from(0)->toMetersPerSecond()],
            [SecPerMile::from(482.802), SecPerKm::from(300)->toImperial()],
            [SecPerMile::from(0), SecPerKm::from(0)->toImperial()],
        ];
    }

    #[DataProvider(methodName: 'provideNauticalMeasurements')]
    public function testNauticalUnitsAreNeverConverted(NauticalMile|Knot $measurement): void
    {
        foreach (UnitSystem::cases() as $unitSystem) {
            $this->assertSame(
                $measurement,
                $measurement->toUnitSystem($unitSystem)
            );
        }
    }

    public static function provideNauticalMeasurements(): array
    {
        return [
            [NauticalMile::from(10)],
            [Knot::from(10)],
        ];
    }

    #[DataProvider(methodName: 'provideMeasurements')]
    public function testRepresentation(Unit $measurement, string $expectedSymbol, string $expectedValue): void
    {
        $this->assertSame($expectedSymbol, $measurement->getSymbol());
        $this->assertEquals((float) $expectedValue, $measurement->toFloat());
        $this->assertSame($expectedValue, (string) $measurement);
        $this->assertSame($expectedValue, Json::encode($measurement));
    }

    public static function provideMeasurements(): iterable
    {
        yield 'Foot' => [Foot::from(10), 'ft', '10'];
        yield 'Kilometer' => [Kilometer::from(100), 'km', '100'];
        yield 'Meter' => [Meter::from(1000), 'm', '1000'];
        yield 'Mile' => [Mile::from(10000), 'mi', '10000'];
        yield 'NauticalMile' => [NauticalMile::from(100), 'NM', '100'];
        yield 'Kilogram' => [Kilogram::from(200), 'kg', '200'];
        yield 'Pound' => [Pound::from(2000), 'lb', '2000'];
        yield 'Celsius' => [Celsius::from(300), '°C', '300'];
        yield 'Fahrenheit' => [Fahrenheit::from(300), '°F', '300'];
        yield 'KmPerHour' => [KmPerHour::from(30), 'km/h', '30'];
        yield 'MilesPerHour' => [MilesPerHour::from(300), 'mph', '300'];
        yield 'MetersPerSecond' => [MetersPerSecond::from(300), 'm/s', '300'];
        yield 'Knot' => [Knot::from(30), 'kn', '30'];
        yield 'SecPerKm' => [SecPerKm::from(300), 'sec/km', '300'];
        yield 'SecPerMile' => [SecPerMile::from(150), 'sec/mi', '150'];
        yield 'SecPer100Meter' => [SecPer100Meter::from(90), 'sec/100m', '90'];
        yield 'Hour' => [Hour::from(2), 'h', '2'];
        yield 'Minute' => [Minute::from(3), 'min', '3'];
        yield 'Seconds' => [Seconds::from(4), 's', '4'];
    }
}
