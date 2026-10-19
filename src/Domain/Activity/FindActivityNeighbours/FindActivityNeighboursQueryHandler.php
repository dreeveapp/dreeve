<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityNeighbours;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindActivityNeighboursQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindActivityNeighbours);

        $older = $this->findNeighbour($query->getActivityId(), '<', 'DESC');
        $newer = $this->findNeighbour($query->getActivityId(), '>', 'ASC');

        return new FindActivityNeighboursResponse(
            olderActivityId: $older ? ActivityId::fromString($older['activityId']) : null,
            olderActivityName: $older ? $older['name'] : null,
            newerActivityId: $newer ? ActivityId::fromString($newer['activityId']) : null,
            newerActivityName: $newer ? $newer['name'] : null,
        );
    }

    /**
     * @return array{activityId: string, name: string}|false
     */
    private function findNeighbour(ActivityId $activityId, string $operator, string $direction): array|false
    {
        /** @var array{activityId: string, name: string}|false $row */
        $row = $this->connection->executeQuery(
            <<<SQL
                SELECT activityId, name
                FROM Activity
                WHERE startDateTime {$operator} (SELECT startDateTime FROM Activity WHERE activityId = :activityId)
                   OR (startDateTime = (SELECT startDateTime FROM Activity WHERE activityId = :activityId) AND activityId {$operator} :activityId)
                ORDER BY startDateTime {$direction}, activityId {$direction}
                LIMIT 1
            SQL,
            ['activityId' => $activityId]
        )->fetchAssociative();

        return $row;
    }
}
