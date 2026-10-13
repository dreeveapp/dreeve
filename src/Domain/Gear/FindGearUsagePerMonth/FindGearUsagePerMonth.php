<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindGearUsagePerMonth;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonthResponse>
 */
final readonly class FindGearUsagePerMonth implements Query
{
}
