<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\NauticalMile;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\Knot;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Twig\MeasurementTwigExtension;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MeasurementTwigExtensionTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'provideConversions')]
    public function testDoConversion(Unit $expectedMeasurement, UnitSystem $unitSystem, Unit $measurementToConvert): void
    {
        $extension = $this->extensionFor($unitSystem);

        $this->assertEquals(
            $expectedMeasurement,
            $extension->convertMeasurement($measurementToConvert)
        );
    }

    public function testFormatPace(): void
    {
        $extension = $this->extensionFor(UnitSystem::METRIC);

        $this->assertEquals(
            '10:00',
            $extension->formatPace(SecPerKm::from(600))
        );
    }

    #[DataProvider(methodName: 'provideVelocities')]
    public function testVelocity(
        string $expectedValue,
        string $expectedSymbol,
        string $expectedRender,
        UnitSystem $unitSystem,
        SecPer100Meter|SecPerKm|Knot|KmPerHour $velocity,
    ): void {
        $extension = $this->extensionFor($unitSystem);

        $this->assertSame($expectedValue, $extension->formatVelocity($velocity));
        $this->assertSame($expectedSymbol, $extension->velocitySymbol($velocity));
        $this->assertSame($expectedRender, $extension->renderVelocity($velocity));
    }

    #[DataProvider(methodName: 'provideMeasurementSymbols')]
    public function testMeasurementSymbol(string $expectedUnitSymbol, UnitSystem $unitSystem, Unit $measurement): void
    {
        $extension = $this->extensionFor($unitSystem);

        $this->assertEquals(
            $expectedUnitSymbol,
            $extension->measurementSymbol($measurement)
        );
    }

    #[DataProvider(methodName: 'provideUnitSymbols')]
    public function testGetUnitSymbol(string $expectedUnitSymbol, UnitSystem $unitSystem, string $unitName): void
    {
        $extension = $this->extensionFor($unitSystem);
        $this->assertEquals(
            $expectedUnitSymbol,
            $extension->getUnitSymbol($unitName),
        );
    }

    public function testGetUnitSymbolItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Invalid unitName "invalid"'));

        $extension = $this->extensionFor(UnitSystem::METRIC);
        $extension->getUnitSymbol('invalid');
    }

    #[DataProvider(methodName: 'provideProximityToMeterFactors')]
    public function testGetProximityToMeterFactor(float $expectedFactor, UnitSystem $unitSystem): void
    {
        $extension = $this->extensionFor($unitSystem);

        $this->assertEqualsWithDelta(
            $expectedFactor,
            $extension->getProximityToMeterFactor(),
            0.0001,
        );
    }

    public function testFormatNumber(): void
    {
        $extension = $this->extensionFor(UnitSystem::METRIC);

        $this->assertEquals(
            "1\u{00A0}000",
            $extension->formatNumber(1000.334, 2)
        );
        $this->assertEquals(
            '10.33',
            $extension->formatNumber(10.334, 2)
        );

        $this->assertEquals(
            0,
            $extension->formatNumber(null, 0)
        );
    }

    private function extensionFor(UnitSystem $unitSystem): MeasurementTwigExtension
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, $unitSystem->value);

        return new MeasurementTwigExtension($settingsRepository);
    }

    public static function provideConversions(): array
    {
        return [
            [Meter::from(3.048), UnitSystem::METRIC, Foot::from(10)],
            [Meter::from(10), UnitSystem::METRIC, Meter::from(10)],
            [Foot::from(9.998964), UnitSystem::IMPERIAL, Meter::from(3.048)],
            [Foot::from(10), UnitSystem::IMPERIAL, Foot::from(10)],
        ];
    }

    public static function provideVelocities(): array
    {
        return [
            ['6:00', '/km', '6:00<span class="text-xxs ml-px">/km</span>', UnitSystem::METRIC, SecPerKm::from(360)],
            ['9:39', '/mi', '9:39<span class="text-xxs ml-px">/mi</span>', UnitSystem::IMPERIAL, SecPerKm::from(360)],
            ['1:35', '/100m', '1:35<span class="text-xxs ml-px">/100m</span>', UnitSystem::METRIC, SecPer100Meter::from(95)],
            ['1:35', '/100m', '1:35<span class="text-xxs ml-px">/100m</span>', UnitSystem::IMPERIAL, SecPer100Meter::from(95)],
            ['22.0', 'km/h', '22.0<span class="text-xxs ml-px whitespace-nowrap">km/h</span>', UnitSystem::METRIC, KmPerHour::from(22)],
            ['13.7', 'mph', '13.7<span class="text-xxs ml-px whitespace-nowrap">mph</span>', UnitSystem::IMPERIAL, KmPerHour::from(22)],
            ['10.0', 'kn', '10.0<span class="text-xxs ml-px whitespace-nowrap">kn</span>', UnitSystem::IMPERIAL, Knot::from(10)],
        ];
    }

    public static function provideMeasurementSymbols(): array
    {
        return [
            ['km', UnitSystem::METRIC, Kilometer::from(10)],
            ['mi', UnitSystem::IMPERIAL, Kilometer::from(10)],
            ['km/h', UnitSystem::METRIC, KmPerHour::from(10)],
            ['mph', UnitSystem::IMPERIAL, KmPerHour::from(10)],
            // Nautical units are unit system agnostic, they never get converted.
            ['NM', UnitSystem::METRIC, NauticalMile::from(10)],
            ['NM', UnitSystem::IMPERIAL, NauticalMile::from(10)],
            ['kn', UnitSystem::METRIC, Knot::from(10)],
            ['kn', UnitSystem::IMPERIAL, Knot::from(10)],
        ];
    }

    public static function provideProximityToMeterFactors(): array
    {
        return [
            [1.0, UnitSystem::METRIC],
            [0.3048, UnitSystem::IMPERIAL],
        ];
    }

    public static function provideUnitSymbols(): array
    {
        return [
            ['km', UnitSystem::METRIC, 'distance'],
            ['mi', UnitSystem::IMPERIAL, 'distance'],
            ['m', UnitSystem::METRIC, 'elevation'],
            ['ft', UnitSystem::IMPERIAL, 'elevation'],
            ['m', UnitSystem::METRIC, 'proximity'],
            ['ft', UnitSystem::IMPERIAL, 'proximity'],
            ['/km', UnitSystem::METRIC, 'pace'],
            ['/mi', UnitSystem::IMPERIAL, 'pace'],
        ];
    }
}
