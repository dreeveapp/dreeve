<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Time\Duration;

final readonly class Cacheability
{
    private function __construct(
        private string $cacheKey,
        private CacheTags $cacheTags,
        private CacheContexts $cacheContexts,
        private ?Duration $ttl,
    ) {
    }

    public static function for(
        string $cacheKey,
        CacheTags $cacheTags,
        ?CacheContexts $cacheContexts = null,
        ?Duration $ttl = null,
    ): self {
        return new self(
            cacheKey: $cacheKey,
            cacheTags: CacheTags::of(...RootCacheTag::crossCutting(), ...$cacheTags->toArray()),
            cacheContexts: $cacheContexts ?? CacheContexts::none(),
            ttl: $ttl,
        );
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    public function getCacheTags(): CacheTags
    {
        return $this->cacheTags;
    }

    public function getCacheContexts(): CacheContexts
    {
        return $this->cacheContexts;
    }

    public function getTtl(): ?Duration
    {
        return $this->ttl;
    }
}
