<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

interface InvalidatesCacheTags
{
    public function getCacheTagsToInvalidate(): CacheTags;
}
