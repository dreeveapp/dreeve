<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Render;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Time\Duration;

final readonly class RenderCache
{
    private const string CHARACTERS_RESERVED_IN_A_CACHE_KEY = '/[{}()\/\\\\@:\s\x00-\x1F]+/';
    private const int LONGEST_REPORTABLE_TTL_IN_SECONDS = 365 * 86400;

    public function __construct(
        #[Autowire(service: 'render.cache')]
        private TagAwareAdapterInterface $cache,
        #[Autowire(service: 'render.cache.tags')]
        private AdapterInterface $tags,
    ) {
    }

    /**
     * @param \Closure(): ?string $callback
     */
    public function get(string $cacheKey, Cacheability $cacheability, \Closure $callback): Render
    {
        $prefixedCacheKey = (string) preg_replace(
            self::CHARACTERS_RESERVED_IN_A_CACHE_KEY,
            '_',
            AppVersion::getSemanticVersion().'.'.$cacheKey
        );

        $item = $this->cache->getItem($prefixedCacheKey);
        if ($item->isHit()) {
            /** @var string|null $cached */
            $cached = $item->get();

            return Render::servedFromCache(
                content: $cached,
                cacheKey: $prefixedCacheKey,
                cacheTags: $this->storedCacheTags($item) ?? $cacheability->getCacheTags()->toTagStrings(),
                ttl: $this->remainingTtl($item),
            );
        }

        $rendered = $callback();
        $ttl = $cacheability->getTtl();

        $item->set($rendered);
        if (!$cacheability->getCacheTags()->isEmpty()) {
            $item->tag($cacheability->getCacheTags()->toTagStrings());
        }
        $item->expiresAfter($ttl?->seconds);
        $this->cache->save($item);

        return Render::freshlyRendered(
            content: $rendered,
            cacheKey: $prefixedCacheKey,
            cacheTags: $cacheability->getCacheTags()->toTagStrings(),
            ttl: $ttl,
        );
    }

    public function invalidateTags(CacheTag ...$cacheTags): void
    {
        if ([] === $cacheTags) {
            return;
        }

        $this->cache->invalidateTags(CacheTags::of(...$cacheTags)->toTagStrings());
    }

    public function clear(): void
    {
        $this->cache->clear();
        $this->tags->clear();
    }

    public function prune(): void
    {
        if (!$this->cache instanceof PruneableInterface) {
            return;
        }

        $this->cache->prune();
    }

    /**
     * @return string[]|null
     */
    private function storedCacheTags(CacheItemInterface $item): ?array
    {
        if (!$item instanceof ItemInterface) {
            return null;
        }

        /** @var array<string, string>|null $tags */
        $tags = $item->getMetadata()[ItemInterface::METADATA_TAGS] ?? null;

        return is_null($tags) ? null : array_values($tags);
    }

    private function remainingTtl(CacheItemInterface $item): ?Duration
    {
        if (!$item instanceof ItemInterface) {
            return null;
        }

        $expiry = $item->getMetadata()[ItemInterface::METADATA_EXPIRY] ?? null;
        if (!is_numeric($expiry)) {
            return null;
        }

        $remainingSeconds = max(0, (int) ceil($expiry - microtime(true)));

        // A render without a TTL never expires, which metadata cannot express: Symfony round trips
        // it as a far future timestamp instead.
        return $remainingSeconds > self::LONGEST_REPORTABLE_TTL_IN_SECONDS ? null : Duration::fromSeconds($remainingSeconds);
    }
}
