<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityGearUsage;
use App\Domain\Activity\Shifting\GearPosition;

final class ActivityGearUsageBuilder
{
    private ActivityId $activityId;
    private GearPosition $position = GearPosition::REAR;
    private int $gearNumber = 6;
    private int $teeth = 16;
    private int $timeInSeconds = 7219;
    private int $shiftCount = 124;

    private function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('test');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityGearUsage
    {
        return ActivityGearUsage::create(
            activityId: $this->activityId,
            position: $this->position,
            gearNumber: $this->gearNumber,
            teeth: $this->teeth,
            timeInSeconds: $this->timeInSeconds,
            shiftCount: $this->shiftCount,
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withGear(GearPosition $position, int $gearNumber, int $teeth): self
    {
        $this->position = $position;
        $this->gearNumber = $gearNumber;
        $this->teeth = $teeth;

        return $this;
    }

    public function withTimeInSeconds(int $timeInSeconds): self
    {
        $this->timeInSeconds = $timeInSeconds;

        return $this;
    }

    public function withShiftCount(int $shiftCount): self
    {
        $this->shiftCount = $shiftCount;

        return $this;
    }
}
