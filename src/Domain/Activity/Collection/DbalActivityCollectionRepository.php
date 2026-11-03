<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalActivityCollectionRepository extends DbalRepository implements ActivityCollectionRepository
{
    public function add(ActivityCollection $activityCollection): void
    {
        $sql = 'INSERT INTO ActivityCollection (activityCollectionId, name, description, type, activityIds, createdOn)
                VALUES (:activityCollectionId, :name, :description, :type, :activityIds, :createdOn)';

        $this->connection->executeStatement($sql, [
            'activityCollectionId' => $activityCollection->getId(),
            'name' => $activityCollection->getName(),
            'description' => $activityCollection->getDescription(),
            'type' => $activityCollection->getType()->value,
            'activityIds' => Json::encode($activityCollection->getActivityIds()),
            'createdOn' => $activityCollection->getCreatedOn(),
        ]);
    }

    public function update(ActivityCollection $activityCollection): void
    {
        $sql = 'UPDATE ActivityCollection SET
                    name = :name,
                    description = :description,
                    type = :type,
                    activityIds = :activityIds
                WHERE activityCollectionId = :activityCollectionId';

        $this->connection->executeStatement($sql, [
            'activityCollectionId' => $activityCollection->getId(),
            'name' => $activityCollection->getName(),
            'description' => $activityCollection->getDescription(),
            'type' => $activityCollection->getType()->value,
            'activityIds' => Json::encode($activityCollection->getActivityIds()),
        ]);
    }

    public function delete(ActivityCollection $activityCollection): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ActivityCollection WHERE activityCollectionId = :activityCollectionId',
            ['activityCollectionId' => $activityCollection->getId()],
        );
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
            createdOn: SerializableDateTime::fromString($result['createdOn']),
        );
    }
}
