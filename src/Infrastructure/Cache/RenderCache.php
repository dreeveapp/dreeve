<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\AppVersion;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RenderCache
{
    private const string CHARACTERS_RESERVED_IN_A_CACHE_KEY = '/[{}()\/\\\\@:\s\x00-\x1F]+/';

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

        $prefixedCacheKey = (string) preg_replace(
            self::CHARACTERS_RESERVED_IN_A_CACHE_KEY,
            '_',
            AppVersion::getSemanticVersion().'.'.$cacheKey
        );

        $item = $this->cache->getItem($prefixedCacheKey);
        if ($item->isHit()) {
            /** @var string|null $cached */
            $cached = $item->get();

            return Render::servedFromCache($cached, $prefixedCacheKey);
        }

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
    }

    public function prune(): void
    {
        if (!$this->cache instanceof PruneableInterface) {
            return;
        }

        $this->cache->prune();
    }
}
