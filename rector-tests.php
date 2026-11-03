<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: __DIR__.'/var/cache/rector/tests',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withComposerBased(phpunit: true);
