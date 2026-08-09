<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class CacheContextRegistry
{
    /**
     * @param iterable<CacheContext> $cacheContexts
     */
    public function __construct(
        #[AutowireIterator('app.cache_context')]
        private iterable $cacheContexts,
    ) {
    }

    public function buildCacheKeySegments(CacheContexts $cacheContexts): string
    {
        $segments = '';
        foreach ($cacheContexts->toArray() as $cacheContextClassName) {
            $cacheContext = $this->find($cacheContextClassName);
            $segments .= sprintf('.%s=%s', $cacheContext::getKey(), $cacheContext->resolve());
        }

        return $segments;
    }

    /**
     * @return iterable<CacheContext>
     */
    public function all(): iterable
    {
        return $this->cacheContexts;
    }

    /**
     * @param class-string<CacheContext> $cacheContextClassName
     */
    private function find(string $cacheContextClassName): CacheContext
    {
        foreach ($this->cacheContexts as $cacheContext) {
            if ($cacheContext::class === $cacheContextClassName) {
                return $cacheContext;
            }
        }

        throw new \RuntimeException(sprintf('Cache context "%s" is not registered', $cacheContextClassName));
    }
}
