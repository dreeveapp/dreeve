<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;

interface ActivityExplorerTilesRepository
{
    public function add(ActivityExplorerTiles $activityExplorerTiles): void;

    public function deleteForActivity(ActivityId $activityId): void;

    /**
     * @return array<string, list<int>>
     */
    public function findAllOnHeatmap(): array;
}
