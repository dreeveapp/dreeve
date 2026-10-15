<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;

final readonly class ActivityExclusionSettings
{
    private function __construct(
        private ActivityIds $activitiesExcludedFromPeakPowerOutputs,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $excludedActivityIds = $data[SettingsName::ACTIVITIES_EXCLUDED_FROM_PEAK_POWER_OUTPUTS->value] ?? [];
        $excludedActivityIds = array_values(array_filter(
            array_map(
                static fn (mixed $id): string => trim((string) $id),
                is_array($excludedActivityIds) ? $excludedActivityIds : []
            ),
            static fn (string $id): bool => '' !== $id
        ));

        return new self(
            activitiesExcludedFromPeakPowerOutputs: ActivityIds::fromArray(array_map(ActivityId::fromString(...), $excludedActivityIds)),
        );
    }

    public function getExcludedFromPeakPowerOutputs(): ActivityIds
    {
        return $this->activitiesExcludedFromPeakPowerOutputs;
    }
}
