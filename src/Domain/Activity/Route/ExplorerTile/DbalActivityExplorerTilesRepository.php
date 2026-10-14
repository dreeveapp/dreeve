<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
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
        $sql = 'INSERT INTO ActivityExplorerTiles (activityId, tileCount, tiles)
                VALUES (:activityId, :tileCount, :tiles)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityExplorerTiles->getActivityId(),
            'tileCount' => $activityExplorerTiles->getTileCount(),
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
}
