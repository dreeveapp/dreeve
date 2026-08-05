<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Cacheable;

final class CacheableStub implements Cacheable
{
    public int $renderCount = 0;
    public ?string $rendered = 'rendered';

    private function __construct(
        private readonly Cacheability $cacheability,
    ) {
    }

    public static function for(Cacheability $cacheability): self
    {
        return new self(
            cacheability: $cacheability,
        );
    }

    public function getCacheability(): Cacheability
    {
        return $this->cacheability;
    }

    public function render(): ?string
    {
        ++$this->renderCount;

        return $this->rendered;
    }
}
