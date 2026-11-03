<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Php85\Rector\FuncCall\OrdSingleByteRector;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: __DIR__.'/var/cache/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withComposerBased(phpunit: true)
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
        RemoveUnusedPrivatePropertyRector::class => [
            __DIR__.'/tests/Infrastructure/CQRS/Command/Bus/RunAnOperation/RunAnOperation.php',
        ],
    ]);
