<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityTotals;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\FindActivityTotals\FindActivityTotalsResponse>
 */
final readonly class FindActivityTotals implements Query
{
}
