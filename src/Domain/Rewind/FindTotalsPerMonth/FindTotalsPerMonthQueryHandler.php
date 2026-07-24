<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindTotalsPerMonth;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindTotalsPerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindTotalsPerMonth);

        $totals = $this->connection->executeQuery(
            <<<SQL
                SELECT SUM(distance) as distance, SUM(elevation) as elevation, SUM(movingTimeInSeconds) as movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchAssociative() ?: [];

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT CAST(strftime('%m', startDateTime) AS INTEGER) AS monthNumber, sportType, SUM(distance) as distance, SUM(elevation) as elevation, SUM(movingTimeInSeconds) as movingTimeInSeconds
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                GROUP BY sportType, monthNumber
                ORDER BY sportType ASC, monthNumber ASC
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        $distancePerMonth = [];
        $elevationPerMonth = [];
        $movingTimePerMonth = [];
        foreach ($results as $result) {
            $monthNumber = $result['monthNumber'];
            $sportType = SportType::from($result['sportType']);

            $distancePerMonth[] = [$monthNumber, $sportType, Meter::from($result['distance'])->toKilometer()];
            $elevationPerMonth[] = [$monthNumber, $sportType, Meter::from($result['elevation'])];
            $movingTimePerMonth[] = [$monthNumber, $sportType, (int) $result['movingTimeInSeconds']];
        }

        return new FindTotalsPerMonthResponse(
            distancePerMonth: $distancePerMonth,
            elevationPerMonth: $elevationPerMonth,
            movingTimePerMonth: $movingTimePerMonth,
            totalDistance: Meter::from((int) ($totals['distance'] ?? 0))->toKilometer(),
            totalElevation: Meter::from((int) ($totals['elevation'] ?? 0)),
            totalMovingTime: (int) ($totals['movingTimeInSeconds'] ?? 0),
        );
    }
}
