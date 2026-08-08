<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class ScopedCacheTag implements CacheTag
{
    private function __construct(
        private RootCacheTag $rootCacheTag,
        private string $scope,
    ) {
    }

    public static function for(RootCacheTag $rootCacheTag, string $scope): self
    {
        return new self(
            rootCacheTag: $rootCacheTag,
            scope: $scope,
        );
    }

    public function toTagString(): string
    {
        return $this->rootCacheTag->value.'.'.$this->scope;
    }
}
