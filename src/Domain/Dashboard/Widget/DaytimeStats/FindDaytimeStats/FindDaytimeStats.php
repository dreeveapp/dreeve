<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats\FindDaytimeStatsResponse>
 */
final readonly class FindDaytimeStats implements Query
{
}
