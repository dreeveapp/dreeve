<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Query\Bus\FindSomething;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<FindSomethingResponse>
 */
class FindSomething implements Query
{
}
