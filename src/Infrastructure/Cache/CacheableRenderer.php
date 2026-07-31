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

    public function render(Cacheable $cacheable): ?string
    {
        $cacheability = $cacheable->getCacheability();

        return $this->renderCache->get(
            $cacheable->getCacheKey().$this->cacheContextRegistry->buildCacheKeySegments($cacheability->getCacheContexts()),
            $cacheability,
            fn (): ?string => $cacheable->render(),
        );
    }
}
