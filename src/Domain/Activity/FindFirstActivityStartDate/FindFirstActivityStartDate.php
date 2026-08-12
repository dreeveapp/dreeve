<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindFirstActivityStartDate;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDateResponse>
 */
final readonly class FindFirstActivityStartDate implements Query
{
}
