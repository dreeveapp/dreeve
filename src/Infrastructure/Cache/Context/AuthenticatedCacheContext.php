<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use App\Infrastructure\Cache\CacheContext;
use App\Infrastructure\Security\AuthenticatedVisitor;

final readonly class AuthenticatedCacheContext implements CacheContext
{
    public function __construct(
        private AuthenticatedVisitor $authenticatedVisitor,
    ) {
    }

    public static function getKey(): string
    {
        return 'auth';
    }

    public function resolve(): string
    {
        return $this->authenticatedVisitor->isAuthenticated() ? 'auth' : 'anon';
    }
}
