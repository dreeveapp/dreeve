<?php

namespace App\Domain\Dashboard\Widget\AthleteProfile\FindAthleteProfileMetrics;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\Connection;

final readonly class FindAthleteProfileMetricsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindAthleteProfileMetrics);

        $queryParams = [
            'startDate' => (string) $query->getFrom()->format('Y-m-d'),
            'endDate' => (string) $query->getTo()->format('Y-m-d'),
        ];

        $aggregatedResult = $this->connection->executeQuery(
            <<<SQL
                SELECT SUM(movingTimeInSeconds) AS movingTime,
                       COUNT(1) as numberOfActivities
                FROM Activity
                WHERE strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
            SQL,
            $queryParams
        )->fetchAssociative();

        $numberOfActiveDays = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*)
                FROM (
                SELECT strftime('%Y-%m-%d', startDateTime) AS date
                      FROM Activity
                      WHERE strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
                      GROUP BY date
               )
            SQL,
            $queryParams
        )->fetchOne();

        $activityMovingTimes = $this->connection->executeQuery(
            <<<SQL
                SELECT activityId, movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
            SQL,
            $queryParams
        )->fetchAllKeyValue();

        $numberOfActivitiesInMostPopularActivityType = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*) as numberOfActivities
                FROM Activity
                WHERE strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
                GROUP BY activityType
                ORDER BY numberOfActivities DESC
                LIMIT 1
            SQL,
            $queryParams
        )->fetchOne();

        return new FindAthleteProfileMetricsResponse(
            activityIds: ActivityIds::fromArray(array_map(
                ActivityId::fromString(...),
                array_keys($activityMovingTimes)
            )),
            movingTime: Seconds::from($aggregatedResult['movingTime'] ?? 0)->toHour(),
            numberOfActivities: $aggregatedResult['numberOfActivities'] ?? 0,
            numberOfActiveDays: $numberOfActiveDays,
            numberOfActivitiesInMostPopularActivityType: $numberOfActivitiesInMostPopularActivityType,
            activityMovingTimesInSeconds: array_values($activityMovingTimes),
        );
    }
}
