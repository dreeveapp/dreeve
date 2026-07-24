<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Measurement\UnitSystem;

interface CombinedActivityStreamRepository
{
    public function add(CombinedActivityStream $combinedActivityStream): void;

    public function findOneForActivityAndUnitSystem(
        ActivityId $activityId,
        UnitSystem $unitSystem,
    ): CombinedActivityStream;

    public function findActivityIdsThatNeedStreamCombining(UnitSystem $unitSystem): ActivityIds;
}
