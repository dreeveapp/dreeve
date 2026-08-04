<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\ActivityIds;

interface GearIdRepository
{
    public function findAll(): GearIds;

    public function findRetired(): GearIds;

    public function findUniqueStravaGearIds(?ActivityIds $restrictToActivityIds): GearIds;
}
