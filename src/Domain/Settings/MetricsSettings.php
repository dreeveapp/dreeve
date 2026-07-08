<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;
use App\Domain\Activity\Stream\ActivitiesExcludedFromPeakPowerOutputs;

final readonly class MetricsSettings
{
    private function __construct(
        private EddingtonConfiguration $eddingtonConfiguration,
        private ActivitiesExcludedFromPeakPowerOutputs $activitiesExcludedFromPeakPowerOutputs,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $eddington = $data['eddington'] ?? [];
        $eddington = array_values(array_map(
            static function (mixed $item): mixed {
                if (!is_array($item)) {
                    return $item;
                }
                $item['showInNavBar'] = filter_var($item['showInNavBar'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item['showInDashboardWidget'] = filter_var($item['showInDashboardWidget'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item['sportTypesToInclude'] = array_values(is_array($item['sportTypesToInclude'] ?? null) ? $item['sportTypesToInclude'] : []);

                return $item;
            },
            is_array($eddington) ? $eddington : []
        ));

        $excludedActivityIds = $data['excludeActivitiesFromPeakPowerOutputs'] ?? [];
        $excludedActivityIds = array_values(array_filter(
            array_map(
                static fn (mixed $id): string => trim((string) $id),
                is_array($excludedActivityIds) ? $excludedActivityIds : []
            ),
            static fn (string $id): bool => '' !== $id
        ));

        return new self(
            eddingtonConfiguration: EddingtonConfiguration::fromScalarArray($eddington),
            activitiesExcludedFromPeakPowerOutputs: ActivitiesExcludedFromPeakPowerOutputs::from($excludedActivityIds),
        );
    }

    public function getEddingtonConfiguration(): EddingtonConfiguration
    {
        return $this->eddingtonConfiguration;
    }

    public function getActivitiesExcludedFromPeakPowerOutputs(): ActivitiesExcludedFromPeakPowerOutputs
    {
        return $this->activitiesExcludedFromPeakPowerOutputs;
    }
}
