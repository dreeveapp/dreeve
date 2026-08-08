<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

/**
 * A label a render is tagged with, so it can be invalidated when the thing it depends on changes.
 *
 * A RootCacheTag covers everything of its kind, a ScopedCacheTag narrows that down to a single part of it.
 * Both are matched by their exact tag string: invalidating a root tag leaves its scoped tags untouched.
 */
interface CacheTag
{
    public function toTagString(): string;
}
