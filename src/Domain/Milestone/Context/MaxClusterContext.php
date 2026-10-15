<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

final readonly class MaxClusterContext implements MilestoneContext
{
    public function __construct(
        private int $tileCount,
    ) {
    }

    public function getTileCount(): int
    {
        return $this->tileCount;
    }
}
