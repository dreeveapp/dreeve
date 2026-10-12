<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\FindGearComponentStats;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindGearComponentStatsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindGearComponentStats);

        [$usageWindowsSql, $usageWindowsParameters] = $query->getUsageWindows()->toSql();
        $result = $this->connection->fetchAssociative(sprintf('
            SELECT SUM(distance) AS distance,
                   SUM(movingTimeInSeconds) AS movingTimeInSeconds,
                   COUNT(*) AS numberOfActivities,
                   MIN(startDateTime) AS firstUsedOn,
                   MAX(startDateTime) AS lastUsedOn
            FROM Activity
            WHERE %s', $usageWindowsSql), $usageWindowsParameters) ?: [];

        return new FindGearComponentStatsResponse(
            distance: Meter::from((float) ($result['distance'] ?? 0))->toKilometer(),
            movingTime: Seconds::from((float) ($result['movingTimeInSeconds'] ?? 0)),
            numberOfActivities: (int) ($result['numberOfActivities'] ?? 0),
            firstUsedOn: isset($result['firstUsedOn']) ? SerializableDateTime::fromString($result['firstUsedOn']) : null,
            lastUsedOn: isset($result['lastUsedOn']) ? SerializableDateTime::fromString($result['lastUsedOn']) : null,
        );
    }
}
