<?php

declare(strict_types=1);

namespace App\Domain\Geography\ExplorerTile;

final readonly class ExplorerTileClusters
{
    public function __construct(
        private ExplorerTiles $clusterTiles,
        private ExplorerTiles $maxCluster,
    ) {
    }

    public function getClusterTiles(): ExplorerTiles
    {
        return $this->clusterTiles;
    }

    public function getMaxCluster(): ExplorerTiles
    {
        return $this->maxCluster;
    }
}
