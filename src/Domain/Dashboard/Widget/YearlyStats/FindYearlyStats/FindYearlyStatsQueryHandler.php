<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStats;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\Year;
use Doctrine\DBAL\Connection;

final readonly class FindYearlyStatsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindYearlyStats);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT strftime('%Y', startDateTime) AS year,
                       activityType,
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS totalMovingTime,
                       SUM(calories) as totalCalories
                FROM Activity
                GROUP BY year, activityType
            SQL,
        )->fetchAllAssociative();

        $statsPerYear = [];

        foreach ($results as $result) {
            $statsPerYear[] = [
                'year' => Year::fromInt((int) $result['year']),
                'activityType' => ActivityType::from($result['activityType']),
                'numberOfActivities' => (int) $result['numberOfActivities'],
                'distance' => Meter::from($result['totalDistance'])->toKilometer(),
                'elevation' => Meter::from($result['totalElevation']),
                'movingTime' => Seconds::from($result['totalMovingTime']),
                'calories' => (int) $result['totalCalories'],
            ];
        }

        return new FindYearlyStatsResponse($statsPerYear);
    }
}
