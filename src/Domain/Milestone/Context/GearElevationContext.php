<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Meter;

final readonly class GearElevationContext implements MilestoneContext
{
    public function __construct(
        private string $gearName,
        private Meter|Foot $threshold,
    ) {
    }

    public function getGearName(): string
    {
        return $this->gearName;
    }

    public function getThreshold(): Meter|Foot
    {
        return $this->threshold;
    }
}
