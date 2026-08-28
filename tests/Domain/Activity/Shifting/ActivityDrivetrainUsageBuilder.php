<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\DrivetrainPosition;

final class ActivityDrivetrainUsageBuilder
{
    private ActivityId $activityId;
    private DrivetrainPosition $position = DrivetrainPosition::REAR;
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

    public function build(): ActivityDrivetrainUsage
    {
        return ActivityDrivetrainUsage::create(
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

    public function withGear(DrivetrainPosition $position, int $gearNumber, int $teeth): self
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
