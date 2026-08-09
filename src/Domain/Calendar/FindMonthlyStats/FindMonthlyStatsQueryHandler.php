<?php

declare(strict_types=1);

namespace App\Domain\Calendar\FindMonthlyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\Month;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindMonthlyStatsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindMonthlyStats);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT strftime('%Y-%m', startDateTime) AS yearAndMonth,
                       sportType,
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS totalMovingTime,
                       SUM(calories) as totalCalories
                FROM Activity
                GROUP BY yearAndMonth, sportType
            SQL,
        )->fetchAllAssociative();

        $statsPerMonth = [];
        foreach ($results as $result) {
            $month = Month::fromDate(SerializableDateTime::fromString(sprintf('%s-01 00:00:00', $result['yearAndMonth'])));
            $sportType = SportType::from($result['sportType']);

            $statsPerMonth[] = [
                'month' => $month,
                'sportType' => $sportType,
                'numberOfActivities' => (int) $result['numberOfActivities'],
                'distance' => Meter::from($result['totalDistance'])->toKilometer(),
                'elevation' => Meter::from($result['totalElevation']),
                'movingTime' => Seconds::from($result['totalMovingTime']),
                'calories' => (int) $result['totalCalories'],
            ];
        }

        $minMaxResults = $this->connection->executeQuery(
            <<<SQL
                SELECT activityType,
                       MIN(startDateTime) AS minStartDate,
                       MAX(startDateTime) AS maxStartDate
                FROM Activity
                GROUP BY activityType
            SQL,
        )->fetchAllAssociative();

        $minMaxDatePerActivityType = [];
        foreach ($minMaxResults as $minMaxResult) {
            $minMaxDatePerActivityType[] = [
                'activityType' => ActivityType::from($minMaxResult['activityType']),
                'min' => Month::fromDate(SerializableDateTime::fromString($minMaxResult['minStartDate'])),
                'max' => Month::fromDate(SerializableDateTime::fromString($minMaxResult['maxStartDate'])),
            ];
        }

        return new FindMonthlyStatsResponse(
            statsPerMonth: $statsPerMonth,
            minMaxMonthPerActivityType: $minMaxDatePerActivityType,
        );
    }
}
