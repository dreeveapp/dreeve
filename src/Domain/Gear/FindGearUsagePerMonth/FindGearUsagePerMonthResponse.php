<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindGearUsagePerMonth;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Overview\MonthlyUsage;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindGearUsagePerMonthResponse implements Response
{
    public function __construct(
        /** @var array<string, MonthlyUsage> */
        private array $usagePerGear,
    ) {
    }

    public function getFor(GearId $gearId): MonthlyUsage
    {
        return $this->usagePerGear[(string) $gearId] ?? throw new \RuntimeException(sprintf('No usage found for gear "%s"', $gearId));
    }
}
