<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php85\Rector\FuncCall\OrdSingleByteRector;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: __DIR__.'/var/cache/rector/src',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSkip([
        OrdSingleByteRector::class,
        ThrowWithPreviousExceptionRector::class,
        RemoveUnusedPublicMethodParameterRector::class,
    ]);
