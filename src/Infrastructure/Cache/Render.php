<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class Render
{
    private function __construct(
        private ?string $content,
        private bool $wasServedFromCache,
        private ?string $cacheKey,
    ) {
    }

    public static function servedFromCache(?string $content, string $cacheKey): self
    {
        return new self(
            content: $content,
            wasServedFromCache: true,
            cacheKey: $cacheKey,
        );
    }

    public static function freshlyRendered(?string $content, string $cacheKey): self
    {
        return new self(
            content: $content,
            wasServedFromCache: false,
            cacheKey: $cacheKey,
        );
    }

    public static function notCacheable(?string $content): self
    {
        return new self(
            content: $content,
            wasServedFromCache: false,
            cacheKey: null,
        );
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function wasServedFromCache(): bool
    {
        return $this->wasServedFromCache;
    }

    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }
}
