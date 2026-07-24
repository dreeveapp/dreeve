<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Time\Hour;

final readonly class CumulativeMovingTimeContext implements MilestoneContext
{
    public function __construct(
        private Hour $threshold,
    ) {
    }

    public function getThreshold(): Hour
    {
        return $this->threshold;
    }
}
