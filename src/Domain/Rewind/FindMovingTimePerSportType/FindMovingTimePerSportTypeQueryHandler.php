<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindMovingTimePerSportType;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindMovingTimePerSportTypeQueryHandler implements QueryHandler
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
        assert($query instanceof FindMovingTimePerSportType);

        $totalMovingTime = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT SUM(movingTimeInSeconds) as movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchOne();

        return new FindMovingTimePerSportTypeResponse(
            movingTimePerSportType: $this->connection->executeQuery(
                <<<SQL
                SELECT sportType, SUM(movingTimeInSeconds) as movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                GROUP BY sportType
                ORDER BY sportType ASC
                SQL,
                [
                    'years' => array_map(strval(...), $query->getYears()->toArray()),
                ],
                [
                    'years' => ArrayParameterType::STRING,
                ]
            )->fetchAllKeyValue(),
            totalMovingTime: $totalMovingTime
        );
    }
}
