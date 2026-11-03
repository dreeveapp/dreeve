<?php

declare(strict_types=1);

namespace App\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Infrastructure\Measurement\UnitSystem;

interface ActivitySplitRepository extends DeletesActivityData
{
    public function findBy(ActivityId $activityId, UnitSystem $unitSystem): ActivitySplits;

    public function add(ActivitySplit $activitySplit): void;

    public function update(ActivitySplit $activitySplit): void;

    public function findActivityIdsWithoutGap(): ActivityIds;

    public function findActivityIdsThatNeedSplitCalculation(): ActivityIds;

    public function isImportedForActivity(ActivityId $activityId): bool;
}
