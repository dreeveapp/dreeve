<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats\FindWeekdayStatsResponse>
 */
final readonly class FindWeekdayStats implements Query
{
}
