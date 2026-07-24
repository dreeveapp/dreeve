<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindGearStatsPerDay;

use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindGearStatsPerDayQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindGearStatsPerDay);

        $sql = <<<'SQL'
                SELECT
                    gearId, DATE(startDateTime) AS startDate,
                    SUM(SUM(distance)) OVER (
                        PARTITION BY gearId
                        ORDER BY DATE(startDateTime)
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS cumulativeDistance
                FROM Activity
                WHERE gearId IS NOT NULL
                GROUP BY startDate, gearId
                ORDER BY gearId, startDate;
                SQL;

        $response = FindGearStatsPerDayResponse::empty();
        if (!$results = $this->connection->executeQuery($sql)->fetchAllAssociative()) {
            return $response;
        }

        foreach ($results as $result) {
            $response->addStat(
                gearId: GearId::fromString($result['gearId']),
                date: SerializableDateTime::fromString($result['startDate']),
                distance: Meter::from($result['cumulativeDistance'])->toKilometer()
            );
        }

        return $response;
    }
}
