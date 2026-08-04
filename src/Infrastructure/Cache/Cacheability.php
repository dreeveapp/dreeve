<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class Cacheability
{
    private function __construct(
        private bool $isCacheable,
        private CacheTags $cacheTags,
        private CacheContexts $cacheContexts,
        private ?int $ttlInSeconds,
    ) {
    }

    public static function for(
        CacheTags $cacheTags,
        ?CacheContexts $cacheContexts = null,
        ?int $ttlInSeconds = null,
    ): self {
        return new self(
            isCacheable: true,
            cacheTags: CacheTags::of(...CacheTag::crossCutting(), ...$cacheTags->toArray()),
            cacheContexts: $cacheContexts ?? CacheContexts::none(),
            ttlInSeconds: $ttlInSeconds,
        );
    }

    public static function none(): self
    {
        return new self(
            isCacheable: false,
            cacheTags: CacheTags::empty(),
            cacheContexts: CacheContexts::none(),
            ttlInSeconds: null,
        );
    }

    public function isCacheable(): bool
    {
        return $this->isCacheable;
    }

    public function getCacheTags(): CacheTags
    {
        return $this->cacheTags;
    }

    public function getCacheContexts(): CacheContexts
    {
        return $this->cacheContexts;
    }

    public function getTtlInSeconds(): ?int
    {
        return $this->ttlInSeconds;
    }
}
