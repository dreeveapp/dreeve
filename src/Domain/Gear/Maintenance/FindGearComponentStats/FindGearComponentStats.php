<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\FindGearComponentStats;

use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Gear\Maintenance\FindGearComponentStats\FindGearComponentStatsResponse>
 */
final readonly class FindGearComponentStats implements Query
{
    public function __construct(
        private UsageWindows $usageWindows,
    ) {
    }

    public function getUsageWindows(): UsageWindows
    {
        return $this->usageWindows;
    }
}
