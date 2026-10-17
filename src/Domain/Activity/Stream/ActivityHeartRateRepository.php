<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Athlete\HeartRateZone\RelativeHeartRateDistribution;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZonesForRollingWindow;

interface ActivityHeartRateRepository
{
    public function findTotalTimeInSecondsInHeartRateZonesForActivity(ActivityId $activityId): TimeInHeartRateZones;

    public function findTimeInHeartRateZonesForLast30Days(): TimeInHeartRateZonesForRollingWindow;

    public function findRelativeHeartRateDistribution(): RelativeHeartRateDistribution;
}
