<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindAvailableRewindOptions;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use Doctrine\DBAL\Connection;

final readonly class FindAvailableRewindOptionsQueryHandler implements QueryHandler
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
        assert($query instanceof FindAvailableRewindOptions);

        $years = $this->connection->executeQuery(
            'SELECT DISTINCT strftime("%Y",startDateTime) AS year FROM Activity
             ORDER BY year DESC',
        )->fetchFirstColumn();

        $allYears = Years::fromArray(array_map(
            Year::fromInt(...),
            $years
        ));
        $options = [
            FindAvailableRewindOptions::ALL_TIME => $allYears,
        ];

        foreach ($years as $year) {
            $options[$year] = Years::fromArray([Year::fromInt((int) $year)]);
        }

        return new FindAvailableRewindOptionsResponse($options);
    }
}
