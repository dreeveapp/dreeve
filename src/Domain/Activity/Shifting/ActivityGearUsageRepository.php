<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;

interface ActivityGearUsageRepository
{
    public function add(ActivityGearUsage $activityGearUsage): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function findByActivity(ActivityId $activityId): ActivityGearUsages;
}
