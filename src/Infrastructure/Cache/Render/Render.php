<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Render;

use Time\Duration;

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
        private ?Duration $ttl,
    ) {
    }

    /**
     * @param string[] $cacheTags
     */
    public static function servedFromCache(
        ?string $content,
        string $cacheKey,
        array $cacheTags = [],
        ?Duration $ttl = null): self
    {
        return new self(
            content: $content,
            cacheStatus: CacheStatus::HIT,
            cacheKey: $cacheKey,
            cacheTags: $cacheTags,
            ttl: $ttl,
        );
    }

    /**
     * @param string[] $cacheTags
     */
    public static function freshlyRendered(?string $content, string $cacheKey, array $cacheTags = [], ?Duration $ttl = null): self
    {
        return new self(
            content: $content,
            cacheStatus: CacheStatus::MISS,
            cacheKey: $cacheKey,
            cacheTags: $cacheTags,
            ttl: $ttl,
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

    public function getTtl(): ?Duration
    {
        return $this->ttl;
    }

    /**
     * @return array<string, string>
     */
    public function getCacheHeaders(): array
    {
        $headers = ['X-Dreeve-Cache' => $this->cacheStatus->value];

        if (!is_null($this->cacheKey)) {
            $headers['X-Dreeve-Cache-Key'] = $this->cacheKey;
        }
        if ([] !== $this->cacheTags) {
            $headers['X-Dreeve-Cache-Tags'] = implode(', ', $this->cacheTags);
        }
        if (!is_null($this->ttl)) {
            $headers['X-Dreeve-Cache-TTL'] = (string) $this->ttl->seconds;
        }

        return $headers;
    }
}
