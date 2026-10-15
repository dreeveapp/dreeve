<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;

final readonly class ExplorerTileHistory
{
    public function __construct(
        private ActivityExplorerTilesRepository $activityExplorerTilesRepository,
    ) {
    }

    /**
     * @return list<ExplorerTileHistoryEntry>
     */
    public function calculate(): array
    {
        $entries = [];
        $visitedTiles = [];
        $maxSquareSize = 0;
        $maxClusterSize = 0;
        foreach ($this->activityExplorerTilesRepository->findAllOrderedByStartDateTime() as $activityTiles) {
            $hasNewTiles = false;
            foreach ($activityTiles['tiles']->toArray() as $tile) {
                if (isset($visitedTiles[$tile])) {
                    continue;
                }
                $visitedTiles[$tile] = true;
                $hasNewTiles = true;
            }

            if ($hasNewTiles) {
                $tiles = ExplorerTiles::fromFlippedTileKeys($visitedTiles);
                $maxSquareSize = $tiles->maxSquare()?->getSize() ?? 0;
                $maxClusterSize = $tiles->clusters()->getMaxCluster()->count();
            }

            $entries[] = new ExplorerTileHistoryEntry(
                activityId: $activityTiles['activityId'],
                startDateTime: $activityTiles['startDateTime'],
                maxSquareSize: $maxSquareSize,
                maxClusterSize: $maxClusterSize,
            );
        }

        return $entries;
    }

    public function calculateNewTileCountFor(ActivityId $activityId): int
    {
        $visitedTiles = [];
        foreach ($this->activityExplorerTilesRepository->findAllOrderedByStartDateTime() as $activityTiles) {
            if ((string) $activityTiles['activityId'] !== (string) $activityId) {
                $visitedTiles += array_fill_keys($activityTiles['tiles']->toArray(), true);
                continue;
            }

            return count(array_filter($activityTiles['tiles']->toArray(), fn (int $tile): bool => !isset($visitedTiles[$tile])));
        }

        return 0;
    }
}
