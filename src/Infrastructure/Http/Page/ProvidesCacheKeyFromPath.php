<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

trait ProvidesCacheKeyFromPath
{
    public function getCacheKey(): string
    {
        // PSR-6 reserves {}()/\@:, so "activity/123" has to become "activity_123".
        return str_replace(str_split('{}()/\@:'), '_', $this->getPath());
    }
}
