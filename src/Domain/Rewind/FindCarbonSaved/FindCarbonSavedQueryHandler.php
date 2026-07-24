<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindCarbonSaved;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Mass\Kilogram;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindCarbonSavedQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindCarbonSaved);

        $result = $this->connection->executeQuery(
            <<<SQL
                SELECT SUM(distance)
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                AND isCommute = 1
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchOne();

        $distanceInKm = Meter::from($result ?? 0)->toKilometer();

        return new FindCarbonSavedResponse(Kilogram::from($distanceInKm->toFloat() * 0.2178));
    }
}
