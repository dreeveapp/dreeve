<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

final readonly class CacheContexts
{
    /**
     * @param list<class-string<CacheContext>> $cacheContexts
     */
    private function __construct(
        private array $cacheContexts,
    ) {
    }

    /**
     * @param class-string<CacheContext> ...$cacheContexts
     */
    public static function of(string ...$cacheContexts): self
    {
        $cacheContexts = array_values(array_unique($cacheContexts));
        sort($cacheContexts);

        return new self($cacheContexts);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->cacheContexts;
    }

    /**
     * @return list<class-string<CacheContext>>
     */
    public function toArray(): array
    {
        return $this->cacheContexts;
    }
}
