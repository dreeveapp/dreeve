<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Athlete\Weight\AthleteWeight;
use App\Domain\Settings\AthleteWeightSettings;
use App\Infrastructure\Measurement\UnitSystem;
use PHPUnit\Framework\TestCase;

class AthleteWeightSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $this->assertSame([], AthleteWeightSettings::fromArray(null)->getHistory(UnitSystem::METRIC)->findAll());
        $this->assertSame([], AthleteWeightSettings::fromArray(['weightHistory' => 'invalid'])->getHistory(UnitSystem::METRIC)->findAll());
    }

    public function testItBuildsTheWeightHistoryFromStoredValues(): void
    {
        $weights = AthleteWeightSettings::fromArray([
            'weightHistory' => [
                ['on' => '2024-01-01', 'weight' => 70],
                ['on' => '2024-02-01', 'weight' => '69.5'],
            ],
        ])->getHistory(UnitSystem::METRIC)->findAll();

        $this->assertSame(
            [['on' => '2024-02-01', 'weight' => 69.5], ['on' => '2024-01-01', 'weight' => 70.0]],
            array_values(array_map(fn (AthleteWeight $weight): array => $weight->jsonSerialize(), $weights)),
        );
    }

    public function testManualWeightsOverrideGarminWithoutDiscardingOtherDates(): void
    {
        $settings = AthleteWeightSettings::fromArray([
            'weightHistory' => [['on' => '2026-11-25', 'weight' => 72]],
            'garminWeightHistory' => [
                ['on' => '2026-11-25', 'weightKg' => 70.125],
                ['on' => '2026-11-24', 'weightKg' => 70.5],
            ],
        ]);
        $weights = array_values($settings->getHistory(UnitSystem::METRIC)->findAll());
        $this->assertSame(72.0, $weights[0]->getWeight()->toFloat());
        $this->assertSame(70.5, $weights[1]->getWeight()->toFloat());
        $this->assertFalse($settings->isGarminWeightSyncEnabled());
        $this->assertCount(2, $settings->getGarminHistory(UnitSystem::METRIC)->findAll());
    }

    public function testGarminWeightsRemainInKilogramsWhenAppearanceUsesPounds(): void
    {
        $settings = AthleteWeightSettings::fromArray([
            'garminWeightSyncEnabled' => '1',
            'garminWeightHistory' => [['on' => '2026-11-25', 'weightKg' => 70]],
        ]);
        $weight = array_first($settings->getHistory(UnitSystem::IMPERIAL)->findAll());
        $this->assertNotNull($weight);
        $this->assertEqualsWithDelta(154.3234, $weight->getWeight()->toFloat(), 0.0001);
        $this->assertTrue($settings->isGarminWeightSyncEnabled());
    }
}
