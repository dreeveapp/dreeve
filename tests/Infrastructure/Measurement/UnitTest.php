<?php

namespace App\Tests\Infrastructure\Measurement;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\Mass\Gram;
use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\Mass\Pound;
use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Temperature\Fahrenheit;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\MilesPerHour;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Measurement\Velocity\SecPerMile;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class UnitTest extends TestCase
{
    use MatchesSnapshots;

    private string $snapshotName;

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
            [Kilogram::from(0.1), Gram::from(100)->toKilogram()],
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
        ];
    }

    #[DataProvider(methodName: 'provideMeasurements')]
    public function testElSnapshots(Unit $measurement): void
    {
        $this->snapshotName = new \ReflectionClass($measurement)->getShortName();
        $this->assertMatchesJsonSnapshot([
            'symbol' => $measurement->getSymbol(),
            'float' => $measurement->toFloat(),
            'string' => (string) $measurement,
            'serialize' => Json::encode($measurement),
        ]);
    }

    protected function getSnapshotId(): string
    {
        return new \ReflectionClass($this)->getShortName().'--'.
            $this->name().'--'.
            $this->snapshotName;
    }

    public static function provideMeasurements(): array
    {
        return [
            [Foot::from(10)],
            [Kilometer::from(100)],
            [Meter::from(1000)],
            [Mile::from(10000)],
            [Gram::from(20)],
            [Kilogram::from(200)],
            [Pound::from(2000)],
            [KmPerHour::from(30)],
            [MilesPerHour::from(300)],
            [Celsius::from(300)],
            [Fahrenheit::from(300)],
            [MetersPerSecond::from(300)],
            [SecPerKm::from(300)],
            [SecPerMile::from(150)],
        ];
    }
}
