<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Time\Hour;

final readonly class GearMovingTimeContext implements MilestoneContext
{
    public function __construct(
        private string $gearName,
        private Hour $threshold,
    ) {
    }

    public function getGearName(): string
    {
        return $this->gearName;
    }

    public function getThreshold(): Hour
    {
        return $this->threshold;
    }
}
