<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindActivityCountPerMonth;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindActivityCountPerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param Query<Response> $query
     */
    public function handle(Query $query): Response
    {
        assert($query instanceof FindActivityCountPerMonth);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT CAST(strftime('%m', startDateTime) AS INTEGER) AS monthNumber, sportType, COUNT(1) as count
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                GROUP BY sportType, monthNumber
                ORDER BY sportType ASC, monthNumber ASC
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        return new FindActivityCountPerMonthResponse(
            array_map(
                fn (array $result): array => [
                    $result['monthNumber'],
                    SportType::from($result['sportType']),
                    $result['count'],
                ],
                $results,
            )
        );
    }
}
