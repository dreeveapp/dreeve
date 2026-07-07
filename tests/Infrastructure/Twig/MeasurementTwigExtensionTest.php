<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Twig\MeasurementTwigExtension;
use App\Infrastructure\ValueObject\Measurement\Length\Foot;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Unit;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Measurement\Velocity\SecPerKm;
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
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => $unitSystem->value]);

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

    public static function provideUnitSymbols(): array
    {
        return [
            ['km', UnitSystem::METRIC, 'distance'],
            ['mi', UnitSystem::IMPERIAL, 'distance'],
            ['m', UnitSystem::METRIC, 'elevation'],
            ['ft', UnitSystem::IMPERIAL, 'elevation'],
        ];
    }
}
