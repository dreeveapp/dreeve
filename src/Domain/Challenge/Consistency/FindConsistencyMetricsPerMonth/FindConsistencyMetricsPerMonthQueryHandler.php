<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency\FindConsistencyMetricsPerMonth;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\Month;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindConsistencyMetricsPerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindConsistencyMetricsPerMonth);

        $sportTypes = $query->getSportTypes();

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT strftime('%Y-%m', startDateTime) AS yearAndMonth, 
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance, MAX(distance) AS maxDistance,
                       SUM(elevation) AS totalElevation, MAX(elevation) AS maxElevation,
                       SUM(movingTimeInSeconds) AS movingTime,
                       SUM(calories) AS totalCaloriesBurnt
                FROM Activity
                WHERE SportType IN(:sportTypes)
                GROUP BY yearAndMonth
            SQL,
            [
                'sportTypes' => $sportTypes->map(fn (SportType $sportType) => $sportType->value),
            ],
            [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        $response = [];
        foreach ($results as $result) {
            $month = Month::fromDate(SerializableDateTime::fromString(sprintf('%s-01', $result['yearAndMonth'])));
            $response[$month->getId()] = [
                $result['numberOfActivities'],
                Meter::from($result['totalDistance'])->toKilometer(),
                Meter::from($result['maxDistance'])->toKilometer(),
                Meter::from($result['totalElevation']),
                Meter::from($result['maxElevation']),
                Seconds::from($result['movingTime']),
                $result['totalCaloriesBurnt'],
            ];
        }

        return new FindConsistencyMetricsPerMonthResponse($response);
    }
}
