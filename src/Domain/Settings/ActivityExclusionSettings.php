<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\ActivityExclusion;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;

final readonly class ActivityExclusionSettings
{
    /**
     * @param array<string, ActivityIds> $excludedActivityIds
     */
    private function __construct(
        private array $excludedActivityIds,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $excludedActivityIds = [];
        foreach (ActivityExclusion::cases() as $exclusion) {
            $activityIds = $data[$exclusion->getSettingsName()->value] ?? [];
            $activityIds = array_values(array_filter(
                array_map(
                    static fn (mixed $id): string => trim((string) $id),
                    is_array($activityIds) ? $activityIds : []
                ),
                static fn (string $id): bool => '' !== $id
            ));

            $excludedActivityIds[$exclusion->value] = ActivityIds::fromArray(array_map(ActivityId::fromString(...), $activityIds));
        }

        return new self($excludedActivityIds);
    }

    public function getExcludedActivityIds(ActivityExclusion $exclusion): ActivityIds
    {
        return $this->excludedActivityIds[$exclusion->value];
    }
}
