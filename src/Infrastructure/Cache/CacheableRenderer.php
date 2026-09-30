<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Cache\Render\Render;
use App\Infrastructure\Cache\Render\RenderCache;

final readonly class CacheableRenderer
{
    public function __construct(
        private RenderCache $renderCache,
        private CacheContextRegistry $cacheContextRegistry,
    ) {
    }

    /**
     * @param \Closure(): ?string $render
     */
    public function render(Cacheability $cacheability, \Closure $render): Render
    {
        return $this->renderCache->get(
            cacheKey: $cacheability->getCacheKey().$this->cacheContextRegistry->buildCacheKeySegments($cacheability->getCacheContexts()),
            cacheability: $cacheability,
            callback: $render,
        );
    }
}
