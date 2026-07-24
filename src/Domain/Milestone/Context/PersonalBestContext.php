<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Unit;

final readonly class PersonalBestContext implements MilestoneContext
{
    public function __construct(
        private Unit $distance,
        private Seconds $time,
    ) {
    }

    public function getDistance(): Unit
    {
        return $this->distance;
    }

    public function getTime(): Seconds
    {
        return $this->time;
    }
}
