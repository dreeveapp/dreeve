<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindNumberOfRestDaysQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindNumberOfRestDays);

        $numberOfActiveDays = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*)
                FROM (
                SELECT strftime('%Y-%m-%d', startDateTime) AS date
                      FROM Activity
                      WHERE strftime('%Y-%m-%d', startDateTime) BETWEEN :startDate AND :endDate
                      GROUP BY date
               )
            SQL,
            [
                'startDate' => $query->getDateRange()->getFrom()->format('Y-m-d'),
                'endDate' => $query->getDateRange()->getTill()->format('Y-m-d'),
            ]
        )->fetchOne();

        return new FindNumberOfRestDaysResponse($query->getDateRange()->getNumberOfDays() - $numberOfActiveDays);
    }
}
