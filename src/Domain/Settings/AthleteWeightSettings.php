<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class AthleteWeightSettings
{
    /**
     * @param array<int|string, mixed> $weightHistory
     * @param array<int|string, mixed> $garminWeightHistory
     */
    private function __construct(
        private array $weightHistory,
        private array $garminWeightHistory,
        private bool $garminWeightSyncEnabled,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $weightHistory = $data[SettingsName::WEIGHT_HISTORY->value] ?? [];
        $garminWeightHistory = $data[SettingsName::GARMIN_WEIGHT_HISTORY->value] ?? [];

        return new self(
            is_array($weightHistory) ? $weightHistory : [],
            is_array($garminWeightHistory) ? $garminWeightHistory : [],
            filter_var($data[SettingsName::GARMIN_WEIGHT_SYNC_ENABLED->value] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function getHistory(UnitSystem $unitSystem): AthleteWeightHistory
    {
        // Manual entries come last, so they always win for the same calendar date.
        return AthleteWeightHistory::fromArray(array_merge($this->garminHistoryIn($unitSystem), $this->weightHistory), $unitSystem);
    }

    public function isGarminWeightSyncEnabled(): bool
    {
        return $this->garminWeightSyncEnabled;
    }

    public function getGarminHistory(UnitSystem $unitSystem): AthleteWeightHistory
    {
        return AthleteWeightHistory::fromArray($this->garminHistoryIn($unitSystem), $unitSystem);
    }

    /**
     * @return list<array{on: string, weight: float}>
     */
    private function garminHistoryIn(UnitSystem $unitSystem): array
    {
        $history = [];
        foreach ($this->garminWeightHistory as $entry) {
            if (!is_array($entry) || !is_string($entry['on'] ?? null) || !is_numeric($entry['weightKg'] ?? null)) {
                continue;
            }
            $weight = Kilogram::from((float) $entry['weightKg']);
            $history[] = [
                'on' => $entry['on'],
                'weight' => UnitSystem::IMPERIAL === $unitSystem ? $weight->toPound()->toFloat() : $weight->toFloat(),
            ];
        }

        return $history;
    }
}
