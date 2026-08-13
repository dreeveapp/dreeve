<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

final readonly class TimeInHeartRateZonesForRollingWindow
{
    private function __construct(
        private TimeInHeartRateZones $current,
        private TimeInHeartRateZones $asOfPreviousDay,
    ) {
    }

    public static function create(
        TimeInHeartRateZones $current,
        TimeInHeartRateZones $asOfPreviousDay,
    ): self {
        return new self(
            current: $current,
            asOfPreviousDay: $asOfPreviousDay,
        );
    }

    public function getCurrent(): TimeInHeartRateZones
    {
        return $this->current;
    }

    public function getAsOfPreviousDay(): TimeInHeartRateZones
    {
        return $this->asOfPreviousDay;
    }
}
