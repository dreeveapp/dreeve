<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class Render
{
    private function __construct(
        public ?string $content,
        public bool $wasServedFromCache,
        public ?string $cacheKey,
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
}
