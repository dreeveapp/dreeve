<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindTotalsForActivitiesWithoutGear;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use Doctrine\DBAL\Connection;

final readonly class FindTotalsForActivitiesWithoutGearQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param Query<Response> $query
     */
    public function handle(Query $query): Response
    {
        assert($query instanceof FindTotalsForActivitiesWithoutGear);

        $result = $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*) AS numberOfActivities,
                       COALESCE(SUM(distance), 0) AS distanceInMeter,
                       COALESCE(SUM(movingTimeInSeconds), 0) AS movingTimeInSeconds,
                       COALESCE(SUM(elevation), 0) AS elevationInMeter,
                       COALESCE(SUM(calories), 0) AS calories
                FROM Activity
                WHERE gearId IS NULL
            SQL
        )->fetchAssociative();

        assert(false !== $result);

        return new FindTotalsForActivitiesWithoutGearResponse(
            numberOfActivities: (int) $result['numberOfActivities'],
            distance: Meter::from((float) $result['distanceInMeter']),
            movingTime: Seconds::from((int) $result['movingTimeInSeconds']),
            elevation: Meter::from((float) $result['elevationInMeter']),
            calories: (int) $result['calories'],
        );
    }
}
