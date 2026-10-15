<?php

declare(strict_types=1);

namespace App\Domain\Geography\ExplorerTile;

final readonly class ExplorerTiles
{
    /**
     * @param list<int> $tiles
     */
    private function __construct(
        private array $tiles,
    ) {
    }

    /**
     * @param array<int, true> $tiles
     */
    public static function fromFlippedTileKeys(array $tiles): self
    {
        $tileKeys = array_keys($tiles);
        sort($tileKeys);

        return new self($tileKeys);
    }

    /**
     * @param list<int> $tiles
     */
    public static function fromArray(array $tiles): self
    {
        return new self($tiles);
    }

    /**
     * @param iterable<list<int>> $tileLists
     */
    public static function union(iterable $tileLists): self
    {
        $tiles = [];
        foreach ($tileLists as $tileList) {
            foreach ($tileList as $tile) {
                $tiles[$tile] = true;
            }
        }

        return self::fromFlippedTileKeys($tiles);
    }

    public function without(ExplorerTiles $other): self
    {
        return new self(array_values(array_diff($this->tiles, $other->tiles)));
    }

    public function clusters(): ExplorerTileClusters
    {
        $visitedTiles = array_flip($this->tiles);
        $clusterTiles = [];
        foreach ($this->tiles as $tile) {
            if (array_all(self::neighbours($tile), fn (?int $neighbour): bool => null !== $neighbour && isset($visitedTiles[$neighbour]))) {
                $clusterTiles[$tile] = true;
            }
        }

        $maxCluster = [];
        $seen = [];
        foreach (array_keys($clusterTiles) as $start) {
            if (isset($seen[$start])) {
                continue;
            }

            $seen[$start] = true;
            $cluster = [$start => true];
            $queue = [$start];
            while ([] !== $queue) {
                foreach (self::neighbours(array_pop($queue)) as $neighbour) {
                    if (null === $neighbour || !isset($clusterTiles[$neighbour]) || isset($seen[$neighbour])) {
                        continue;
                    }
                    $seen[$neighbour] = true;
                    $cluster[$neighbour] = true;
                    $queue[] = $neighbour;
                }
            }

            if (count($cluster) > count($maxCluster)) {
                $maxCluster = $cluster;
            }
        }

        return new ExplorerTileClusters(
            clusterTiles: self::fromFlippedTileKeys($clusterTiles),
            maxCluster: self::fromFlippedTileKeys($maxCluster),
        );
    }

    public function maxSquare(): ?ExplorerTileSquare
    {
        $sizes = [];
        $maxSquare = null;
        foreach ($this->tiles as $tile) {
            $x = intdiv($tile, ExplorerTileGrid::TILES_PER_AXIS);
            $y = $tile % ExplorerTileGrid::TILES_PER_AXIS;
            $size = 1 + min(
                $x > 0 ? $sizes[$tile - ExplorerTileGrid::TILES_PER_AXIS] ?? 0 : 0,
                $y > 0 ? $sizes[$tile - 1] ?? 0 : 0,
                $x > 0 && $y > 0 ? $sizes[$tile - ExplorerTileGrid::TILES_PER_AXIS - 1] ?? 0 : 0,
            );
            $sizes[$tile] = $size;

            if (!$maxSquare || $size > $maxSquare->getSize()) {
                $maxSquare = new ExplorerTileSquare(
                    x: $x - $size + 1,
                    y: $y - $size + 1,
                    size: $size,
                );
            }
        }

        return $maxSquare;
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        return $this->tiles;
    }

    public function count(): int
    {
        return count($this->tiles);
    }

    public function isEmpty(): bool
    {
        return [] === $this->tiles;
    }

    /**
     * @return array{?int, ?int, ?int, ?int}
     */
    private static function neighbours(int $tile): array
    {
        $x = intdiv($tile, ExplorerTileGrid::TILES_PER_AXIS);
        $y = $tile % ExplorerTileGrid::TILES_PER_AXIS;

        return [
            $x > 0 ? $tile - ExplorerTileGrid::TILES_PER_AXIS : null,
            $x < ExplorerTileGrid::TILES_PER_AXIS - 1 ? $tile + ExplorerTileGrid::TILES_PER_AXIS : null,
            $y > 0 ? $tile - 1 : null,
            $y < ExplorerTileGrid::TILES_PER_AXIS - 1 ? $tile + 1 : null,
        ];
    }
}
