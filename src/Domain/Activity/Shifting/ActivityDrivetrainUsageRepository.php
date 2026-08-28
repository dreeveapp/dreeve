<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;

interface ActivityDrivetrainUsageRepository
{
    public function add(ActivityDrivetrainUsage $activityDrivetrainUsage): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function findByActivity(ActivityId $activityId): ActivityDrivetrainUsages;
}
