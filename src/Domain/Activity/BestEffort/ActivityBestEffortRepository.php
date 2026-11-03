<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;

interface ActivityBestEffortRepository extends DeletesActivityData
{
    public function add(ActivityBestEffort $activityBestEffort): void;

    public function hasData(): bool;

    public function findActivityIdsThatNeedBestEffortsCalculation(): ActivityIds;

    public function findByActivity(ActivityId $activity): ActivityBestEfforts;
}
