<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindGroupActivityCount;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindGroupActivityCountQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindGroupActivityCount);

        $groupActivityCount = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*)
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                AND isGroupActivity = 1
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchOne();

        return new FindGroupActivityCountResponse($groupActivityCount);
    }
}
