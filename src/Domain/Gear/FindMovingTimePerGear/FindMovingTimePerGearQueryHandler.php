<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindMovingTimePerGear;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindMovingTimePerGearQueryHandler implements QueryHandler
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
        assert($query instanceof FindMovingTimePerGear);

        return new FindMovingTimePerGearResponse($this->connection->executeQuery(
            <<<SQL
                SELECT gearId, SUM(movingTimeInSeconds) as movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                AND gearId IS NOT NULL
                AND activityType IN (:activityType)
                GROUP BY gearId
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
                'activityType' => array_map(
                    fn (ActivityType $activityType): string => $activityType->value,
                    $query->getActivityTypes()?->toArray() ?? ActivityType::cases()
                ),
            ],
            [
                'years' => ArrayParameterType::STRING,
                'activityType' => ArrayParameterType::STRING,
            ]
        )->fetchAllKeyValue());
    }
}
