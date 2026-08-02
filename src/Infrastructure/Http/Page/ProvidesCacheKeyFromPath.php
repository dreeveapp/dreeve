<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

trait ProvidesCacheKeyFromPath
{
    public function getCacheKey(): string
    {
        return $this->getPath();
    }
}
