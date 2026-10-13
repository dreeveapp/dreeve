<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth;

use App\Domain\Gear\MonthlyUsage;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindRecordingDeviceUsagePerMonthQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindRecordingDeviceUsagePerMonth);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT deviceName,
                       strftime('%Y-%m', startDateTime) AS month,
                       SUM(distance) AS distance,
                       MIN(startDateTime) AS firstUsedOn,
                       MAX(startDateTime) AS lastUsedOn,
                       GROUP_CONCAT(DISTINCT sportType) AS sportTypes
                FROM Activity
                WHERE deviceName IS NOT NULL
                GROUP BY deviceName, month
                ORDER BY deviceName, month
            SQL
        )->fetchAllAssociative();

        $resultsPerDevice = [];
        foreach ($results as $result) {
            $resultsPerDevice[$result['deviceName']][] = $result;
        }

        return new FindRecordingDeviceUsagePerMonthResponse(array_map(
            MonthlyUsage::fromQueryResults(...),
            $resultsPerDevice,
        ));
    }
}
