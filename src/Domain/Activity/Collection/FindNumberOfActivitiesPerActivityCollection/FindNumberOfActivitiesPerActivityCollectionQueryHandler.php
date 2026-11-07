<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Doctrine\DBAL\Connection;

final readonly class FindNumberOfActivitiesPerActivityCollectionQueryHandler implements QueryHandler
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
        assert($query instanceof FindNumberOfActivitiesPerActivityCollection);

        return new FindNumberOfActivitiesPerActivityCollectionResponse(array_map(
            intval(...),
            $this->connection->executeQuery(
                'SELECT ActivityCollection.activityCollectionId, COUNT(DISTINCT Activity.activityId)
                FROM ActivityCollection, json_each(ActivityCollection.activityIds) AS member
                INNER JOIN Activity ON Activity.activityId = member.value
                GROUP BY ActivityCollection.activityCollectionId'
            )->fetchAllKeyValue(),
        ));
    }
}
