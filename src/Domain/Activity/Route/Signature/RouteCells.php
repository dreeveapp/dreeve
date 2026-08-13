<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

final readonly class RouteCells
{
    /**
     * @param list<int> $cells
     */
    private function __construct(
        private array $cells,
    ) {
    }

    /**
     * @param array<int, true> $cells
     */
    public static function fromFlippedCellKeys(array $cells): self
    {
        $cellKeys = array_keys($cells);
        sort($cellKeys);

        return new self($cellKeys);
    }

    /**
     * @param list<int> $cells
     */
    public static function fromArray(array $cells): self
    {
        return new self($cells);
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        return $this->cells;
    }

    public function count(): int
    {
        return count($this->cells);
    }

    public function isEmpty(): bool
    {
        return [] === $this->cells;
    }
}
