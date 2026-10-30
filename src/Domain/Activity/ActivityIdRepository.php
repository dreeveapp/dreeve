<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportTypes;

interface ActivityIdRepository
{
    public function count(): int;

    public function findAll(): ActivityIds;

    public function findAllImportedFromStravaApi(): ActivityIds;

    public function hasImportedFromStravaApi(): bool;

    public function hasForSportTypes(SportTypes $sportTypes): bool;

    public function findMarkedForDeletion(): ActivityIds;
}
