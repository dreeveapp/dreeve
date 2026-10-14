<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;
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

    public function findAllOnHeatmap(): array
    {
        $sql = 'SELECT ActivityExplorerTiles.activityId, ActivityExplorerTiles.tiles
                FROM ActivityExplorerTiles
                INNER JOIN Activity ON Activity.activityId = ActivityExplorerTiles.activityId
                WHERE Activity.sportType IN (:sportTypes)
                AND Activity.polyline IS NOT NULL AND Activity.polyline <> ""
                AND Activity.routeGeography IS NOT NULL AND Activity.routeGeography <> ""
                AND JSON_EXTRACT(Activity.routeGeography, "$.country_code") IS NOT NULL
                AND Activity.worldType = :worldType
                ORDER BY ActivityExplorerTiles.activityId';

        $tilesPerActivity = [];
        foreach ($this->connection->executeQuery(
            sql: $sql,
            params: [
                'sportTypes' => array_map(
                    fn (SportType $sportType): string => $sportType->value,
                    array_values(array_filter(
                        SportType::cases(),
                        fn (SportType $sportType): bool => $sportType->supportsReverseGeocoding()
                    ))
                ),
                'worldType' => WorldType::REAL_WORLD->value,
            ],
            types: [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->iterateAssociative() as $result) {
            $tilesPerActivity[(string) $result['activityId']] = array_values(array_map(
                intval(...),
                (array) Json::uncompressAndDecode($result['tiles'])
            ));
        }

        return $tilesPerActivity;
    }
}
