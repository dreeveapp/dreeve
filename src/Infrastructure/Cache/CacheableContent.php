<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class CacheableContent implements Cacheable
{
    /**
     * @param \Closure(): ?string $render
     */
    public function __construct(
        private Cacheability $cacheability,
        private \Closure $render,
    ) {
    }

    public function getCacheability(): Cacheability
    {
        return $this->cacheability;
    }

    public function render(): ?string
    {
        return ($this->render)();
    }
}
