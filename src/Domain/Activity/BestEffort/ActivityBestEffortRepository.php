<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface ActivityBestEffortRepository
{
    public function add(ActivityBestEffort $activityBestEffort): void;

    public function hasData(): bool;

    public function findActivityIdsThatNeedBestEffortsCalculation(): ActivityIds;

    public function findByActivity(ActivityId $activity): ActivityBestEfforts;

    public function findPersonalRecords(): ActivityBestEfforts;

    public function findMostRecentStartDateTimeOfActivitiesWithBestEfforts(): ?SerializableDateTime;

    public function deleteForActivity(ActivityId $activityId): void;
}
