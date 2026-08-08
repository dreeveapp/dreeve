<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class CacheTags
{
    /**
     * @param array<CacheTag|ScopedCacheTag> $cacheTags
     */
    private function __construct(
        private array $cacheTags,
    ) {
    }

    public static function of(CacheTag|ScopedCacheTag ...$cacheTags): self
    {
        return new self($cacheTags);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function merge(self $other): self
    {
        return new self([...$this->cacheTags, ...$other->cacheTags]);
    }

    /**
     * @return array<CacheTag|ScopedCacheTag>
     */
    public function toArray(): array
    {
        return $this->cacheTags;
    }

    public function isEmpty(): bool
    {
        return [] === $this->cacheTags;
    }

    /**
     * @return string[]
     */
    public function toTagStrings(): array
    {
        return array_values(array_unique(array_map(
            fn (CacheTag|ScopedCacheTag $cacheTag): string => $cacheTag->toTagString(),
            $this->cacheTags
        )));
    }
}
