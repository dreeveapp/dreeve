<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

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
                $maxSquareSize = ExplorerTiles::fromFlippedTileKeys($visitedTiles)->maxSquare()?->getSize() ?? 0;
            }

            $entries[] = new ExplorerTileHistoryEntry(
                activityId: $activityTiles['activityId'],
                startDateTime: $activityTiles['startDateTime'],
                maxSquareSize: $maxSquareSize,
            );
        }

        return $entries;
    }
}
