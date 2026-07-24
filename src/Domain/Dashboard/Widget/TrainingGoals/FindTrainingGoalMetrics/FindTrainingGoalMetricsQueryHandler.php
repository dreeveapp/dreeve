<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingGoals\FindTrainingGoalMetrics;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindTrainingGoalMetricsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindTrainingGoalMetrics);

        $sportTypes = $query->getSportTypes();

        $result = $this->connection->executeQuery(
            <<<SQL
                SELECT SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS movingTime,
                       COUNT(1) AS numberOfActivities,
                       SUM(calories) AS calories
                FROM Activity
                WHERE SportType IN(:sportTypes)
                AND strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
            SQL,
            [
                'sportTypes' => $sportTypes->map(fn (SportType $sportType) => $sportType->value),
                'startDate' => (string) $query->getFrom()->format('Y-m-d'),
                'endDate' => (string) $query->getTo()->format('Y-m-d'),
            ],
            [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->fetchAssociative();

        return new FindTrainingGoalMetricsResponse(
            distance: Meter::from($result['totalDistance'] ?? 0)->toKilometer(),
            elevation: Meter::from($result['totalElevation'] ?? 0),
            movingTime: Seconds::from($result['movingTime'] ?? 0),
            numberOfActivities: SimpleUnit::from($result['numberOfActivities'] ?? 0),
            calories: SimpleUnit::from($result['calories'] ?? 0),
        );
    }
}
