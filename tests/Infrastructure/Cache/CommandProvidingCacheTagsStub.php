<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\InvalidatesCacheTags;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class CommandProvidingCacheTagsStub extends DomainCommand implements InvalidatesCacheTags
{
    public function __construct(
        private CacheTag $cacheTag,
    ) {
    }

    public function getCacheTagsToInvalidate(): CacheTags
    {
        return CacheTags::of($this->cacheTag);
    }
}
