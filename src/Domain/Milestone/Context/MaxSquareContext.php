<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

final readonly class MaxSquareContext implements MilestoneContext
{
    public function __construct(
        private int $size,
    ) {
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
