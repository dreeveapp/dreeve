<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;

interface ActivityDrivetrainUsageRepository extends DeletesActivityData
{
    public function add(ActivityDrivetrainUsage $activityDrivetrainUsage): void;

    public function findByActivity(ActivityId $activityId): ActivityDrivetrainUsages;
}
