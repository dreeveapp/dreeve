<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportGear;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearRepository;

final readonly class GearImportStatus
{
    public function __construct(
        private GearIdRepository $gearIdRepository,
        private GearRepository $gearRepository,
    ) {
    }

    public function isComplete(): bool
    {
        $stravaGearIdsOnActivities = $this->gearIdRepository->findUniqueStravaGearIds(null);
        $importedGears = $this->gearRepository->findAll();

        foreach ($stravaGearIdsOnActivities as $gearId) {
            if (!$importedGears->getByGearId($gearId) instanceof Gear) {
                return false;
            }
        }

        return true;
    }
}
