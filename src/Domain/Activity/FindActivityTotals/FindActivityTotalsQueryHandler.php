<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityTotals;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindActivityTotalsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindActivityTotals);

        $result = $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*) AS totalActivities,
                       COALESCE(SUM(distance), 0) AS totalDistanceInMeter,
                       COALESCE(SUM(elevation), 0) AS totalElevationInMeter,
                       COALESCE(SUM(calories), 0) AS totalCalories,
                       COALESCE(SUM(movingTimeInSeconds), 0) AS totalMovingTimeInSeconds,
                       COUNT(DISTINCT strftime('%Y%m%d', startDateTime)) AS totalDaysOfWorkingOut,
                       MIN(startDateTime) AS firstActivityStartDateTime
                FROM Activity
            SQL
        )->fetchAssociative();

        assert(false !== $result);

        return new FindActivityTotalsResponse(
            totalActivities: (int) $result['totalActivities'],
            totalDistance: Meter::from((float) $result['totalDistanceInMeter'])->toKilometer(),
            totalElevation: Meter::from((float) $result['totalElevationInMeter']),
            totalCalories: (int) $result['totalCalories'],
            totalMovingTimeInSeconds: (int) $result['totalMovingTimeInSeconds'],
            totalDaysOfWorkingOut: (int) $result['totalDaysOfWorkingOut'],
            firstActivityStartDate: $result['firstActivityStartDateTime']
                ? SerializableDateTime::fromString($result['firstActivityStartDateTime'])
                : null,
        );
    }
}
