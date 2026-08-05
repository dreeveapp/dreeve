<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington\FindDistancePerDay;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindDistancePerDayQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindDistancePerDay);

        $distancePerDay = $this->connection->executeQuery(
            <<<SQL
                SELECT strftime('%Y-%m-%d', startDateTime) AS day,
                       SUM(distance) AS distanceInMeter
                FROM Activity
                WHERE sportType IN (:sportTypes)
                GROUP BY day
                ORDER BY day ASC
            SQL,
            [
                'sportTypes' => $query->getSportTypes()->map(fn (SportType $sportType): string => $sportType->value),
            ],
            [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->fetchAllKeyValue();

        return new FindDistancePerDayResponse(array_map(
            fn (int|float|string $distanceInMeter): Meter => Meter::from((float) $distanceInMeter),
            $distancePerDay
        ));
    }
}
