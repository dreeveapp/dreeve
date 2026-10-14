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
}
