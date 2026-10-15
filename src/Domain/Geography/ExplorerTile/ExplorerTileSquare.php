<?php

declare(strict_types=1);

namespace App\Domain\Geography\ExplorerTile;

final readonly class ExplorerTileSquare implements \JsonSerializable
{
    public function __construct(
        private int $x,
        private int $y,
        private int $size,
    ) {
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array{x: int, y: int, size: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'size' => $this->size,
        ];
    }
}
