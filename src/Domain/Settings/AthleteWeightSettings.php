<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Infrastructure\Measurement\UnitSystem;

final readonly class AthleteWeightSettings
{
    /**
     * @param array<int|string, mixed> $weightHistory
     */
    private function __construct(
        private array $weightHistory,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $weightHistory = $data[SettingsName::WEIGHT_HISTORY->value] ?? [];

        return new self(is_array($weightHistory) ? $weightHistory : []);
    }

    public function getHistory(UnitSystem $unitSystem): AthleteWeightHistory
    {
        return AthleteWeightHistory::fromArray($this->weightHistory, $unitSystem);
    }
}
