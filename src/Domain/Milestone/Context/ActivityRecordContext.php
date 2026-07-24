<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Unit;

final readonly class ActivityRecordContext implements MilestoneContext
{
    public function __construct(
        private Unit $value,
    ) {
    }

    public function getValue(): Unit
    {
        return $this->value;
    }
}
