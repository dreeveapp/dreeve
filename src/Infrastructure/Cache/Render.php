<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class Render
{
    /**
     * @param string[] $cacheTags
     */
    private function __construct(
        private ?string $content,
        private CacheStatus $cacheStatus,
        private ?string $cacheKey,
        private array $cacheTags,
        private ?int $ttlInSeconds,
    ) {
    }

    /**
     * @param string[] $cacheTags
     */
    public static function servedFromCache(
        ?string $content,
        string $cacheKey,
        array $cacheTags = [],
        ?int $ttlInSeconds = null): self
    {
        return new self(
            content: $content,
            cacheStatus: CacheStatus::HIT,
            cacheKey: $cacheKey,
            cacheTags: $cacheTags,
            ttlInSeconds: $ttlInSeconds,
        );
    }

    /**
     * @param string[] $cacheTags
     */
    public static function freshlyRendered(?string $content, string $cacheKey, array $cacheTags = [], ?int $ttlInSeconds = null): self
    {
        return new self(
            content: $content,
            cacheStatus: CacheStatus::MISS,
            cacheKey: $cacheKey,
            cacheTags: $cacheTags,
            ttlInSeconds: $ttlInSeconds,
        );
    }

    public static function notCacheable(?string $content): self
    {
        return new self(
            content: $content,
            cacheStatus: CacheStatus::UNCACHEABLE,
            cacheKey: null,
            cacheTags: [],
            ttlInSeconds: null,
        );
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getCacheStatus(): CacheStatus
    {
        return $this->cacheStatus;
    }

    public function wasServedFromCache(): bool
    {
        return CacheStatus::HIT === $this->cacheStatus;
    }

    /**
     * @return string[]
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }

    public function getTtlInSeconds(): ?int
    {
        return $this->ttlInSeconds;
    }

    /**
     * @return array<string, string>
     */
    public function getCacheHeaders(): array
    {
        $headers = ['X-Cache' => $this->cacheStatus->value];

        if (!is_null($this->cacheKey)) {
            $headers['X-Cache-Key'] = $this->cacheKey;
        }
        if ([] !== $this->cacheTags) {
            $headers['X-Cache-Tags'] = implode(', ', $this->cacheTags);
        }
        if (!is_null($this->ttlInSeconds)) {
            $headers['X-Cache-TTL'] = (string) $this->ttlInSeconds;
        }

        return $headers;
    }
}
