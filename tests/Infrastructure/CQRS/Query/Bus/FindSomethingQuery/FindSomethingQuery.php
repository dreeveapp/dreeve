<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Query\Bus\FindSomethingQuery;

use App\Infrastructure\CQRS\Query\Query;
use App\Tests\Infrastructure\CQRS\Query\Bus\FindSomething\FindSomethingResponse;

/**
 * @implements Query<FindSomethingResponse>
 */
class FindSomethingQuery implements Query
{
}
