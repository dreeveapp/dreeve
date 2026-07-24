<?php

declare(strict_types=1);

namespace App\Domain\Zwift\FindZwiftStatsPerWorld;

use App\Domain\Activity\WorldType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\Connection;

final readonly class FindZwiftStatsPerWorldQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindZwiftStatsPerWorld);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT JSON_EXTRACT(routeGeography, '$.state') as zwiftWorld,
                       COUNT(*) AS numberOfActivities,
                       SUM(distance) AS totalDistance,
                       SUM(elevation) AS totalElevation,
                       SUM(movingTimeInSeconds) AS totalMovingTime,
                       SUM(calories) as totalCalories
                FROM Activity
                WHERE worldType = :worldType AND zwiftWorld IS NOT NULL
                GROUP BY zwiftWorld
                ORDER BY numberOfActivities DESC
            SQL,
            [
                'worldType' => WorldType::ZWIFT->value,
            ],
        )->fetchAllAssociative();

        $statsPerWorld = [];
        foreach ($results as $result) {
            $zwiftWorld = $result['zwiftWorld'];
            assert(is_string($zwiftWorld));
            $statsPerWorld[] = [
                'zwiftWorld' => $zwiftWorld,
                'numberOfActivities' => (int) $result['numberOfActivities'],
                'distance' => Meter::from($result['totalDistance'])->toKilometer(),
                'elevation' => Meter::from($result['totalElevation']),
                'movingTime' => Seconds::from($result['totalMovingTime']),
                'calories' => (int) $result['totalCalories'],
            ];
        }

        return new FindZwiftStatsPerWorldResponse($statsPerWorld);
    }
}
