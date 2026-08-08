<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class ScopedCacheTag
{
    private function __construct(
        private CacheTag $cacheTag,
        private string $scope,
    ) {
    }

    public static function for(CacheTag $cacheTag, string $scope): self
    {
        return new self(
            cacheTag: $cacheTag,
            scope: $scope,
        );
    }

    public function toTagString(): string
    {
        return $this->cacheTag->value.'.'.$this->scope;
    }
}
