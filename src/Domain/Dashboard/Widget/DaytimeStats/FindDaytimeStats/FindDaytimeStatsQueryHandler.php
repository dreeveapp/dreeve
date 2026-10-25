<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\Connection;

final readonly class FindDaytimeStatsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindDaytimeStats);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT activityType,
                       CAST(strftime('%H', startDateTime) AS INTEGER) AS hour,
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS totalMovingTime
                FROM Activity
                GROUP BY activityType, hour
            SQL,
        )->fetchAllAssociative();

        $statsPerHour = [];
        foreach ($results as $result) {
            $statsPerHour[] = [
                'activityType' => ActivityType::from($result['activityType']),
                'hour' => (int) $result['hour'],
                'numberOfActivities' => (int) $result['numberOfActivities'],
                'distance' => Meter::from($result['totalDistance'])->toKilometer(),
                'elevation' => Meter::from($result['totalElevation']),
                'movingTime' => Seconds::from($result['totalMovingTime']),
            ];
        }

        return new FindDaytimeStatsResponse($statsPerHour);
    }
}
