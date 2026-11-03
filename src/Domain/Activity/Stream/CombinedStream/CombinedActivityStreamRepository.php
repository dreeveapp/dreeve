<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Infrastructure\Measurement\UnitSystem;

interface CombinedActivityStreamRepository extends DeletesActivityData
{
    public function add(CombinedActivityStream $combinedActivityStream): void;

    public function findOneForActivityAndUnitSystem(
        ActivityId $activityId,
        UnitSystem $unitSystem,
    ): CombinedActivityStream;

    public function findActivityIdsThatNeedStreamCombining(UnitSystem $unitSystem): ActivityIds;

    public function countChartableStreamTypesFor(ActivityId $activityId, UnitSystem $unitSystem): int;

    public function hasStreamTypeFor(ActivityId $activityId, UnitSystem $unitSystem, CombinedStreamType $streamType): bool;
}
