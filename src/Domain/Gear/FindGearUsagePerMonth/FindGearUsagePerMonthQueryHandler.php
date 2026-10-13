<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindGearUsagePerMonth;

use App\Domain\Gear\GearId;
use App\Domain\Gear\MonthlyUsage;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindGearUsagePerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindGearUsagePerMonth);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT gearId,
                       strftime('%Y-%m', startDateTime) AS month,
                       SUM(distance) AS distance,
                       MAX(startDateTime) AS lastUsedOn,
                       GROUP_CONCAT(DISTINCT sportType) AS sportTypes
                FROM Activity
                GROUP BY gearId, month
                ORDER BY gearId, month
            SQL
        )->fetchAllAssociative();

        $resultsPerGear = [];
        foreach ($results as $result) {
            $resultsPerGear[$result['gearId'] ?? (string) GearId::none()][] = $result;
        }

        return new FindGearUsagePerMonthResponse(array_map(
            MonthlyUsage::fromQueryResults(...),
            $resultsPerGear,
        ));
    }
}
