<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;

final readonly class EddingtonCalculator
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return list<Eddington>
     */
    public function calculate(UnitSystem $unitSystem): array
    {
        $eddingtons = [];
        foreach ($this->settingsRepository->metrics()->getEddingtonConfiguration() as $eddingtonConfigItem) {
            $activities = $this->enrichedActivities->findBySportTypes($eddingtonConfigItem->getSportTypesToInclude());
            if ($activities->isEmpty()) {
                continue;
            }

            $eddington = Eddington::getInstance(
                activities: $activities,
                config: $eddingtonConfigItem,
                unitSystem: $unitSystem
            );
            if ($eddington->getNumber() <= 0) {
                continue;
            }
            $eddingtons[] = $eddington;
        }

        return $eddingtons;
    }
}
