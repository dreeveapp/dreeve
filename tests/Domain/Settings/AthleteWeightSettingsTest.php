<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\AthleteWeightSettings;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
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
            array_values(array_map(fn ($weight) => $weight->jsonSerialize(), $weights)),
        );
    }

    public function testTheWeightHistoryIsStoredInTheAthleteWeightGroup(): void
    {
        $this->assertSame(SettingsGroup::ATHLETE_WEIGHT, SettingsName::WEIGHT_HISTORY->group());
    }
}
