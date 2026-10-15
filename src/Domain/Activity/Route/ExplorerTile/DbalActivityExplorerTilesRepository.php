<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalActivityExplorerTilesRepository extends DbalRepository implements ActivityExplorerTilesRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(ActivityExplorerTiles $activityExplorerTiles): void
    {
        $sql = 'INSERT INTO ActivityExplorerTiles (activityId, tiles)
                VALUES (:activityId, :tiles)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityExplorerTiles->getActivityId(),
            'tiles' => Json::encodeAndCompress($activityExplorerTiles->getTiles()->toArray()),
        ]);

        $this->eventBus->publishEvents($activityExplorerTiles->getRecordedEvents());
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityExplorerTiles WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function findAll(): array
    {
        $sql = 'SELECT activityId, tiles FROM ActivityExplorerTiles ORDER BY activityId';

        $tilesPerActivity = [];
        foreach ($this->connection->executeQuery($sql)->iterateAssociative() as $result) {
            $tilesPerActivity[(string) $result['activityId']] = array_values(array_map(
                intval(...),
                (array) Json::uncompressAndDecode($result['tiles'])
            ));
        }

        return $tilesPerActivity;
    }

    public function findAllOrderedByStartDateTime(): array
    {
        $sql = 'SELECT ActivityExplorerTiles.activityId, Activity.startDateTime, ActivityExplorerTiles.tiles
                FROM ActivityExplorerTiles
                INNER JOIN Activity ON Activity.activityId = ActivityExplorerTiles.activityId
                ORDER BY Activity.startDateTime ASC, ActivityExplorerTiles.activityId ASC';

        $results = [];
        foreach ($this->connection->executeQuery($sql)->iterateAssociative() as $result) {
            $results[] = [
                'activityId' => ActivityId::fromString($result['activityId']),
                'startDateTime' => SerializableDateTime::fromString($result['startDateTime']),
                'tiles' => ExplorerTiles::fromArray(array_values(array_map(
                    intval(...),
                    (array) Json::uncompressAndDecode($result['tiles'])
                ))),
            ];
        }

        return $results;
    }
}
