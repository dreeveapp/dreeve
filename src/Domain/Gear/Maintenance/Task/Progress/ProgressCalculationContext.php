<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ProgressCalculationContext
{
    private function __construct(
        private UsageWindows $usageWindows,
        private SerializableDateTime $lastTaggedOn,
        private IntervalUnit $intervalUnit,
        private int $intervalValue,
    ) {
    }

    public static function from(
        UsageWindows $usageWindows,
        SerializableDateTime $lastTaggedOn,
        IntervalUnit $intervalUnit,
        int $intervalValue,
    ): self {
        return new self(
            usageWindows: $usageWindows,
            lastTaggedOn: $lastTaggedOn,
            intervalUnit: $intervalUnit,
            intervalValue: $intervalValue,
        );
    }

    public function getUsageWindows(): UsageWindows
    {
        return $this->usageWindows;
    }

    public function getLastTaggedOn(): SerializableDateTime
    {
        return $this->lastTaggedOn;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }
}
