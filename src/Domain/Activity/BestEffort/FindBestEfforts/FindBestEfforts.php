<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort\FindBestEfforts;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\BestEffort\FindBestEfforts\FindBestEffortsResponse>
 */
final readonly class FindBestEfforts implements Query
{
}
