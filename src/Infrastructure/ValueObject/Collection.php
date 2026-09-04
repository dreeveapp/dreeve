<?php

namespace App\Infrastructure\ValueObject;

/**
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
abstract class Collection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /** @var array<T> */
    private array $items = [];

    abstract public function getItemClassName(): string;

    public static function empty(): static
    {
        return new static([]);
    }

    /**
     * @param array<T> $items
     */
    public static function fromArray(array $items): static
    {
        return new static($items);
    }

    /**
     * @param array<T> $items
     */
    final private function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * @param T $item
     */
    public function has(mixed $item): bool
    {
        $this->guardItemIsInstanceOfItemClassName($item);

        return \in_array($item, $this->items);
    }

    /**
     * @param T $item
     */
    public function add(mixed $item): static
    {
        $this->guardItemIsInstanceOfItemClassName($item);
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param array<T> $items
     */
    public function addMultiple(array $items): static
    {
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    public function remove(int|string $index): static
    {
        if (array_key_exists((string) $index, $this->items)) {
            unset($this->items[$index]);
        }

        $this->items = array_values($this->items);

        return $this;
    }

    /**
     * @param T $itemToReplace
     * @param T $itemTobeReplacedWith
     */
    public function replace(mixed $itemToReplace, mixed $itemTobeReplacedWith): static
    {
        $this->guardItemIsInstanceOfItemClassName($itemTobeReplacedWith);

        $index = array_search($itemToReplace, $this->items);
        if (false === $index) {
            throw new \InvalidArgumentException('Could not replace item, item not found in collection');
        }

        $this->items[$index] = $itemTobeReplacedWith;

        return $this;
    }

    /**
     * @param Collection<T> $collection
     */
    public function mergeWith(Collection $collection): static
    {
        foreach ($collection as $item) {
            $this->add($item);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return array<T>
     */
    public function toArray(): array
    {
        return \array_values($this->items);
    }

    /**
     * @return T|null
     */
    public function getFirst(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $items = $this->toArray();
        /** @var T $item */
        $item = reset($items);

        return $item;
    }

    /**
     * @return T|null
     */
    public function getLast(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $items = $this->toArray();
        /** @var T $item */
        $item = end($items);

        return $item;
    }

    /**
     * @return T|null
     */
    public function find(\Closure $closure): mixed
    {
        return array_find($this->items, fn (mixed $item): mixed => $closure($item));
    }

    public function reverse(): static
    {
        return static::fromArray(array_reverse($this->items));
    }

    /**
     * @return array<mixed>
     */
    public function map(\Closure $closure): array
    {
        return array_map(fn (mixed $item): mixed => $closure($item), $this->items);
    }

    public function sum(\Closure $closure): int|float
    {
        /** @var array<float|int> $numbers */
        $numbers = $this->map(fn (mixed $item): int|float => $closure($item) ?? 0);

        return array_sum($numbers);
    }

    public function max(\Closure $closure): mixed
    {
        /** @var non-empty-array<float|int> $numbers */
        $numbers = $this->map(fn (mixed $item): int|float => $closure($item));

        return max($numbers);
    }

    public function min(\Closure $closure): mixed
    {
        /** @var non-empty-array<float|int> $numbers */
        $numbers = $this->map(fn (mixed $item): int|float => $closure($item));

        return min($numbers);
    }

    public function filter(?\Closure $closure = null): static
    {
        if (is_null($closure)) {
            return static::fromArray(array_filter($this->items));
        }

        return static::fromArray(array_filter($this->items, fn (mixed $item): mixed => $closure($item)));
    }

    public function usort(\Closure $closure): static
    {
        usort($this->items, fn (mixed $a, mixed $b) => $closure($a, $b));

        return static::fromArray($this->items);
    }

    public function slice(int $offset, ?int $length = null, bool $preserve_keys = false): static
    {
        return static::fromArray(
            array_slice($this->items, $offset, $length, $preserve_keys)
        );
    }

    public function unique(): static
    {
        return static::fromArray(array_unique($this->items));
    }

    /**
     * @return array<T>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param T $item
     */
    private function guardItemIsInstanceOfItemClassName(mixed $item): void
    {
        $itemClassName = $this->getItemClassName();
        if (!$item instanceof $itemClassName) {
            throw new \InvalidArgumentException(sprintf('Item must be an instance of %s', $itemClassName));
        }
    }
}
