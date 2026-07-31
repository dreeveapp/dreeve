<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\AppVersion;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;

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
    public function get(string $cacheKey, Cacheability $cacheability, \Closure $callback): ?string
    {
        if (!$cacheability->isCacheable()) {
            return $callback();
        }

        $item = $this->cache->getItem($this->buildCacheKey($cacheKey));
        if ($item->isHit()) {
            /** @var string|null $cached */
            $cached = $item->get();

            return $cached;
        }

        // A render legitimately being null (a widget without data) must be cached too, otherwise
        // the cheapest looking renders are the ones re-running their queries on every request.
        $rendered = $callback();

        assert($item instanceof ItemInterface);
        $item->set($rendered);
        if (!$cacheability->getCacheTags()->isEmpty()) {
            $item->tag($cacheability->getCacheTags()->toTagStrings());
        }
        if (!is_null($ttlInSeconds = $cacheability->getTtlInSeconds())) {
            $item->expiresAfter($ttlInSeconds);
        }
        $this->cache->save($item);

        return $rendered;
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
        assert($item instanceof ItemInterface);
        $item->set(AppVersion::getSemanticVersion());
        $this->cache->save($item);
    }
}
