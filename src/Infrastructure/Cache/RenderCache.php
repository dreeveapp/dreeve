<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\AppVersion;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RenderCache
{
    private const string CHARACTERS_RESERVED_IN_A_CACHE_KEY = '/[{}()\/\\\\@:\s\x00-\x1F]+/';

    public function __construct(
        #[Autowire(service: 'render.cache')]
        private TagAwareAdapterInterface $cache,
        #[Autowire('%app.render_cache.default_lifetime%')]
        private int $selfHealingTtlInSeconds,
    ) {
    }

    /**
     * @param \Closure(): ?string $callback
     */
    public function get(string $cacheKey, Cacheability $cacheability, \Closure $callback): Render
    {
        if (!$cacheability->isCacheable()) {
            return Render::notCacheable($callback());
        }

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
                ttlInSeconds: $this->remainingTtlInSeconds($item),
            );
        }

        $rendered = $callback();
        $ttlInSeconds = $cacheability->getTtlInSeconds() ?? $this->selfHealingTtlInSeconds;

        $item->set($rendered);
        if (!$cacheability->getCacheTags()->isEmpty()) {
            $item->tag($cacheability->getCacheTags()->toTagStrings());
        }
        $item->expiresAfter($ttlInSeconds);
        $this->cache->save($item);

        return Render::freshlyRendered(
            content: $rendered,
            cacheKey: $prefixedCacheKey,
            cacheTags: $cacheability->getCacheTags()->toTagStrings(),
            ttlInSeconds: $ttlInSeconds,
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

    private function remainingTtlInSeconds(CacheItemInterface $item): ?int
    {
        if (!$item instanceof ItemInterface) {
            return null;
        }

        $expiry = $item->getMetadata()[ItemInterface::METADATA_EXPIRY] ?? null;
        if (!is_numeric($expiry)) {
            return null;
        }

        return max(0, (int) ceil($expiry - microtime(true)));
    }
}
