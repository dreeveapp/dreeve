<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class CacheableRenderer
{
    public function __construct(
        private RenderCache $renderCache,
        private CacheContextRegistry $cacheContextRegistry,
    ) {
    }

    public function render(Cacheable $cacheable): Render
    {
        $cacheability = $cacheable->getCacheability();

        return $this->renderCache->get(
            cacheKey: $cacheability->getCacheKey().$this->cacheContextRegistry->buildCacheKeySegments($cacheability->getCacheContexts()),
            cacheability: $cacheability,
            callback: fn (): ?string => $cacheable->render(),
        );
    }
}
