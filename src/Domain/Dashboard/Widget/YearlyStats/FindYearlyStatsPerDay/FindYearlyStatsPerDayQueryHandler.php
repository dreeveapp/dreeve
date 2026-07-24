<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStatsPerDay;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindYearlyStatsPerDayQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindYearlyStatsPerDay);

        $sql = <<<'SQL'
                SELECT
                    strftime('%Y', startDateTime) AS year, DATE(startDateTime) AS startDate,
                    activityType,
                    SUM(SUM(distance)) OVER (
                        PARTITION BY strftime('%Y', startDateTime), activityType
                        ORDER BY DATE(startDateTime)
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS cumulativeDistance,
                    SUM(SUM(movingTimeInSeconds)) OVER (
                        PARTITION BY strftime('%Y', startDateTime), activityType
                        ORDER BY DATE(startDateTime)
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS cumulativeMovingTime,
                    SUM(SUM(elevation)) OVER (
                        PARTITION BY strftime('%Y', startDateTime), activityType
                        ORDER BY DATE(startDateTime)
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS cumulativeElevation
                FROM Activity
                GROUP BY startDate, activityType
                ORDER BY startDate DESC;
                SQL;

        $response = FindYearlyStatsPerDayResponse::empty();
        if (!$results = $this->connection->executeQuery($sql)->fetchAllAssociative()) {
            return $response;
        }

        foreach ($results as $result) {
            $response->add(
                date: SerializableDateTime::fromString($result['startDate']),
                activityType: ActivityType::from($result['activityType']),
                distance: Meter::from($result['cumulativeDistance'])->toKilometer(),
                movingTime: Seconds::from($result['cumulativeMovingTime']),
                elevation: Meter::from($result['cumulativeElevation']),
            );
        }

        return $response;
    }
}
