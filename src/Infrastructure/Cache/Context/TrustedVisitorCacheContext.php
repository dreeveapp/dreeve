<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use App\Infrastructure\Security\TrustedVisitor;

final readonly class TrustedVisitorCacheContext implements CacheContext
{
    public function __construct(
        private TrustedVisitor $trustedVisitor,
    ) {
    }

    public static function getKey(): string
    {
        return 'trust';
    }

    public function resolve(): string
    {
        return $this->trustedVisitor->isTrusted() ? 'trusted' : 'anonymized';
    }
}
