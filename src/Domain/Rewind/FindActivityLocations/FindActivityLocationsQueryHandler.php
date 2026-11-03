<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindActivityLocations;

use App\Domain\Activity\WorldType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindActivityLocationsQueryHandler implements QueryHandler
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
        assert($query instanceof FindActivityLocations);
        /** @var array<int, array{0: float, 1: float, 2: int}> $results */
        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT startingCoordinateLongitude, startingCoordinateLatitude, numberOfActivities FROM
                (
                    SELECT
                           MIN(activityId) as activityId,
                           COALESCE(JSON_EXTRACT(routeGeography, '$.city'), JSON_EXTRACT(routeGeography, '$.county'), JSON_EXTRACT(routeGeography, '$.municipality')) as selectedLocation,
                           COUNT(*) as numberOfActivities
                    FROM Activity
                    WHERE (JSON_EXTRACT(routeGeography, '$.city') IS NOT NULL OR JSON_EXTRACT(routeGeography, '$.county') OR JSON_EXTRACT(routeGeography, '$.municipality'))
                    AND strftime('%Y',startDateTime) IN (:years)
                    AND worldType = :worldType
                    GROUP BY selectedLocation
                ) tmp
                INNER JOIN Activity ON tmp.activityId = Activity.activityId
                ORDER BY numberOfActivities DESC
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
                'worldType' => WorldType::REAL_WORLD->value,
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchAllNumeric();

        return new FindActivityLocationsResponse($results);
    }
}
