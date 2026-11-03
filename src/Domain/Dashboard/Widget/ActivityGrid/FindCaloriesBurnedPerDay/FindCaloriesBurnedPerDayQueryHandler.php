<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\ActivityGrid\FindCaloriesBurnedPerDay;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindCaloriesBurnedPerDayQueryHandler implements QueryHandler
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
        assert($query instanceof FindCaloriesBurnedPerDay);

        return new FindCaloriesBurnedPerDayResponse($this->connection->executeQuery(
            <<<SQL
                SELECT
                    strftime('%Y-%m-%d', startDateTime) AS date,
                    SUM(calories) AS caloriesBurned
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                GROUP BY date
                ORDER BY date DESC
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchAllKeyValue());
    }
}
