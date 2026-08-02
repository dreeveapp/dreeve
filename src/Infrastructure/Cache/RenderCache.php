<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\AppVersion;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RenderCache
{
    private const string APP_VERSION_CACHE_KEY = 'app-version';

    public function __construct(
        #[Autowire(service: 'render.cache')]
        private TagAwareAdapterInterface $cache,
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

        $prefixedCacheKey = $this->buildCacheKey($cacheKey);

        $item = $this->cache->getItem($prefixedCacheKey);
        if ($item->isHit()) {
            /** @var string|null $cached */
            $cached = $item->get();

            return Render::servedFromCache($cached, $prefixedCacheKey);
        }

        // A render being null (a widget without data) must be cached too
        $rendered = $callback();

        $item->set($rendered);
        if (!$cacheability->getCacheTags()->isEmpty()) {
            $item->tag($cacheability->getCacheTags()->toTagStrings());
        }
        if (!is_null($ttlInSeconds = $cacheability->getTtlInSeconds())) {
            $item->expiresAfter($ttlInSeconds);
        }
        $this->cache->save($item);

        return Render::freshlyRendered($rendered, $prefixedCacheKey);
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
        $this->markAppVersion();
    }

    public function clearWhenAppVersionChanged(): bool
    {
        $item = $this->cache->getItem(self::APP_VERSION_CACHE_KEY);
        if ($item->isHit() && $item->get() === AppVersion::getSemanticVersion()) {
            return false;
        }

        $this->clear();

        return true;
    }

    private function buildCacheKey(string $cacheKey): string
    {
        return AppVersion::getSemanticVersion().'.'.$cacheKey;
    }

    private function markAppVersion(): void
    {
        $item = $this->cache->getItem(self::APP_VERSION_CACHE_KEY);
        $item->set(AppVersion::getSemanticVersion());
        $this->cache->save($item);
    }
}
