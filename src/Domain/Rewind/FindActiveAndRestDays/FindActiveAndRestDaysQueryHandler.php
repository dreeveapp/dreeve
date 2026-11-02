<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindActiveAndRestDays;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindActiveAndRestDaysQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
        private Clock $clock,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindActiveAndRestDays);

        $numberOfActiveDays = (int) $this->connection->executeQuery(
            <<<SQL
                SELECT COUNT(*)
                FROM (
                    SELECT strftime('%Y-%m-%d', startDateTime) AS date
                          FROM Activity
                          WHERE strftime('%Y', startDateTime) IN (:years)
                          GROUP BY date
                )
            SQL,
            [
                'years' => array_map(strval(...), $query->getYears()->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchOne();

        $firstActivityStartDate = SerializableDateTime::fromString($this->connection->executeQuery(
            <<<SQL
                SELECT MIN(startDateTime) AS date FROM Activity
            SQL,
        )->fetchOne());

        /** @var \App\Infrastructure\ValueObject\Time\Year $mostRecentYearToQuery */
        $mostRecentYearToQuery = $query->getYears()->getFirst();
        /** @var \App\Infrastructure\ValueObject\Time\Year $oldestYearToQuery */
        $oldestYearToQuery = $query->getYears()->getLast();
        $today = $this->clock->now();

        $startDate = $oldestYearToQuery->toInt() === $firstActivityStartDate->getYear() ? $firstActivityStartDate : SerializableDateTime::fromString(sprintf('%s-01-01 00:00:00', $oldestYearToQuery->toInt()));
        $endDate = $mostRecentYearToQuery->toInt() === $today->getYear() ? SerializableDateTime::fromString($today->format('Y-m-d 23:59:59')) : SerializableDateTime::fromString(sprintf('%s-12-31 23:59:59', $mostRecentYearToQuery->toInt()));

        return new FindActiveAndRestDaysResponse(
            totalNumberOfDays: $startDate->diff($endDate)->days + 1,
            activeDays: $numberOfActiveDays,
        );
    }
}
