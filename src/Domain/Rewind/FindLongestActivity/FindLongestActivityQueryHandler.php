<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindLongestActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Exception\EntityNotFound;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindLongestActivityQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindLongestActivity);

        $years = $query->getYears();
        if (!$activityId = $this->connection->executeQuery(
            <<<SQL
                SELECT activityId
                FROM Activity
                WHERE strftime('%Y',startDateTime) IN (:years)
                ORDER BY movingTimeInSeconds DESC
                LIMIT 1
            SQL,
            [
                'years' => array_map(strval(...), $years->toArray()),
            ],
            [
                'years' => ArrayParameterType::STRING,
            ]
        )->fetchOne()) {
            throw new EntityNotFound('Could not determine longest activity');
        }

        return new FindLongestActivityResponse(
            $this->activityRepository->find(ActivityId::fromString($activityId)),
        );
    }
}
