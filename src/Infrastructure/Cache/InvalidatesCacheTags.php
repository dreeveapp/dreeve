<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class InvalidatesCacheTags
{
    /**
     * @var CacheTag[]
     */
    public array $cacheTags;

    public function __construct(CacheTag ...$cacheTags)
    {
        $this->cacheTags = $cacheTags;
    }
}
