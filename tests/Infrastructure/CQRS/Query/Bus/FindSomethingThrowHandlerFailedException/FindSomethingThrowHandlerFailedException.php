<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Query\Bus\FindSomethingThrowHandlerFailedException;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\Response;

/**
 * @implements Query<Response>
 */
final readonly class FindSomethingThrowHandlerFailedException implements Query
{
}
