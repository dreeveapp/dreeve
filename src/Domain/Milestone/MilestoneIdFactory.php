<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

final class MilestoneIdFactory
{
    private int $counter = 0;

    public function next(): MilestoneId
    {
        return MilestoneId::fromString('milestone-'.++$this->counter);
    }
}
