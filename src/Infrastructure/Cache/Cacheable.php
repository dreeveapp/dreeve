<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

interface Cacheable
{
    public function getCacheability(): Cacheability;

    public function render(): ?string;
}
