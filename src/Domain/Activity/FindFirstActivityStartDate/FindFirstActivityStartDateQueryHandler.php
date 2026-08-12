<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindFirstActivityStartDate;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindFirstActivityStartDateQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindFirstActivityStartDate);

        $startDate = $this->connection->executeQuery(
            <<<SQL
                SELECT MIN(startDateTime) FROM Activity
            SQL
        )->fetchOne();

        return new FindFirstActivityStartDateResponse(
            is_string($startDate) ? SerializableDateTime::fromString($startDate) : null
        );
    }
}
