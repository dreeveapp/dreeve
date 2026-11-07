<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalActivityCollectionRepository extends DbalRepository implements ActivityCollectionRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(ActivityCollection $activityCollection): void
    {
        $sql = 'INSERT INTO ActivityCollection (activityCollectionId, name, description, type, activityIds, coverImagePath, useRouteMapAsCover, createdOn)
                VALUES (:activityCollectionId, :name, :description, :type, :activityIds, :coverImagePath, :useRouteMapAsCover, :createdOn)';

        $this->connection->executeStatement($sql, [
            'activityCollectionId' => $activityCollection->getId(),
            'name' => $activityCollection->getName(),
            'description' => $activityCollection->getDescription(),
            'type' => $activityCollection->getType()->value,
            'activityIds' => Json::encode($activityCollection->getActivityIds()),
            'coverImagePath' => $activityCollection->getCoverImagePath(),
            'useRouteMapAsCover' => (int) $activityCollection->useRouteMapAsCover(),
            'createdOn' => $activityCollection->getCreatedOn(),
        ]);

        $this->eventBus->publishEvents($activityCollection->getRecordedEvents());
    }

    public function update(ActivityCollection $activityCollection): void
    {
        $sql = 'UPDATE ActivityCollection SET
                    name = :name,
                    description = :description,
                    type = :type,
                    activityIds = :activityIds,
                    coverImagePath = :coverImagePath,
                    useRouteMapAsCover = :useRouteMapAsCover
                WHERE activityCollectionId = :activityCollectionId';

        $this->connection->executeStatement($sql, [
            'activityCollectionId' => $activityCollection->getId(),
            'name' => $activityCollection->getName(),
            'description' => $activityCollection->getDescription(),
            'type' => $activityCollection->getType()->value,
            'activityIds' => Json::encode($activityCollection->getActivityIds()),
            'coverImagePath' => $activityCollection->getCoverImagePath(),
            'useRouteMapAsCover' => (int) $activityCollection->useRouteMapAsCover(),
        ]);

        $this->eventBus->publishEvents($activityCollection->getRecordedEvents());
    }

    public function delete(ActivityCollection $activityCollection): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ActivityCollection WHERE activityCollectionId = :activityCollectionId',
            ['activityCollectionId' => $activityCollection->getId()],
        );

        $this->eventBus->publishEvents($activityCollection->getRecordedEvents());
    }

    public function find(ActivityCollectionId $activityCollectionId): ActivityCollection
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM ActivityCollection WHERE activityCollectionId = :activityCollectionId',
            ['activityCollectionId' => $activityCollectionId],
        )->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('ActivityCollection "%s" not found', $activityCollectionId));
        }

        return $this->hydrate($result);
    }

    public function findAll(): ActivityCollections
    {
        $results = $this->connection->executeQuery(
            'SELECT * FROM ActivityCollection ORDER BY createdOn DESC'
        )->fetchAllAssociative();

        return ActivityCollections::fromArray(array_map(
            $this->hydrate(...),
            $results,
        ));
    }

    public function findForActivity(ActivityId $activityId): ActivityCollections
    {
        return $this->findAll()->filter(
            static fn (ActivityCollection $activityCollection): bool => $activityCollection->getActivityIds()->has($activityId)
        );
    }

    public function hasActivityCollectionsWithActivities(): bool
    {
        return (bool) $this->connection->executeQuery(
            'SELECT EXISTS(
                SELECT 1 FROM ActivityCollection, json_each(ActivityCollection.activityIds) AS member
                INNER JOIN Activity ON Activity.activityId = member.value
            )'
        )->fetchOne();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityCollection
    {
        return ActivityCollection::fromState(
            activityCollectionId: ActivityCollectionId::fromString($result['activityCollectionId']),
            name: (string) $result['name'],
            description: $result['description'],
            type: ActivityCollectionType::from($result['type']),
            activityIds: ActivityIds::fromArray(array_map(
                ActivityId::fromString(...),
                Json::decode($result['activityIds']),
            )),
            coverImagePath: $result['coverImagePath'],
            useRouteMapAsCover: (bool) $result['useRouteMapAsCover'],
            createdOn: SerializableDateTime::fromString($result['createdOn']),
        );
    }
}
