<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

/**
 * @template T of Item
 *
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Overview implements \IteratorAggregate
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        private Pagination $pagination,
        private int $total,
        private array $items)
    {
    }

    /**
     * @template TItem of Item
     *
     * @param list<TItem> $items
     */
    public static function create(
        Pagination $pagination,
        int $total,
        array $items,
    ): self {
        return new self(
            pagination: $pagination,
            total: $total,
            items: $items,
        );
    }

    /**
     * @return list<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPagination(): Pagination
    {
        return $this->pagination;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
