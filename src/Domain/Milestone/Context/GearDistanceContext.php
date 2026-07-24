<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Mile;

final readonly class GearDistanceContext implements MilestoneContext
{
    public function __construct(
        private string $gearName,
        private Kilometer|Mile $threshold,
    ) {
    }

    public function getGearName(): string
    {
        return $this->gearName;
    }

    public function getThreshold(): Kilometer|Mile
    {
        return $this->threshold;
    }
}
