<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;

interface ActivityLapRepository extends DeletesActivityData
{
    public function findBy(ActivityId $activityId): ActivityLaps;

    public function add(ActivityLap $lap): void;

    public function isImportedForActivity(ActivityId $activityId): bool;
}
