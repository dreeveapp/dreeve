<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;

interface ActivityHeartRateRepository
{
    public function findTotalTimeInSecondsInHeartRateZones(): TimeInHeartRateZones;

    /**
     * @return array<string, TimeInHeartRateZones> keyed by ActivityType->value
     */
    public function findTotalTimeInSecondsInHeartRateZonesPerActivityType(): array;

    public function findTotalTimeInSecondsInHeartRateZonesForActivity(ActivityId $activityId): TimeInHeartRateZones;

    public function findTotalTimeInSecondsInHeartRateZonesForLast30Days(): TimeInHeartRateZones;
}
