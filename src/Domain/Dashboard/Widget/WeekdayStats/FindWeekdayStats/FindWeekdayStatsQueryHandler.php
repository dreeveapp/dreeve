<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\Widget\WeekdayStats\Weekday;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\Connection;

final readonly class FindWeekdayStatsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindWeekdayStats);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT activityType,
                       CAST(strftime('%w', startDateTime) AS INTEGER) AS weekday,
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS totalMovingTime
                FROM Activity
                GROUP BY activityType, weekday
            SQL,
        )->fetchAllAssociative();

        $statsPerWeekday = [];
        foreach ($results as $result) {
            $statsPerWeekday[] = [
                'activityType' => ActivityType::from($result['activityType']),
                'weekday' => Weekday::from((int) $result['weekday']),
                'numberOfActivities' => (int) $result['numberOfActivities'],
                'distance' => Meter::from($result['totalDistance'])->toKilometer(),
                'elevation' => Meter::from($result['totalElevation']),
                'movingTime' => Seconds::from($result['totalMovingTime']),
            ];
        }

        return new FindWeekdayStatsResponse($statsPerWeekday);
    }
}
