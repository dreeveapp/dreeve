<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface ActivityExplorerTilesRepository
{
    public function add(ActivityExplorerTiles $activityExplorerTiles): void;

    public function deleteForActivity(ActivityId $activityId): void;

    /**
     * @return array<string, list<int>>
     */
    public function findAll(): array;

    /**
     * @return list<array{activityId: ActivityId, startDateTime: SerializableDateTime, tiles: ExplorerTiles}>
     */
    public function findAllOrderedByStartDateTime(): array;
}
