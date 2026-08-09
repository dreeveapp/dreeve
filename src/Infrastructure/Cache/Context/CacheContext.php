<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.cache_context')]
interface CacheContext
{
    public static function getKey(): string;

    public function resolve(): string;
}
