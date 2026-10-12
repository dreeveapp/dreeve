<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use App\Infrastructure\Security\Visitor;

final readonly class TrustedVisitorCacheContext implements CacheContext
{
    public function __construct(
        private Visitor $visitor,
    ) {
    }

    public static function getKey(): string
    {
        return 'trust';
    }

    public function resolve(): string
    {
        return $this->visitor->isTrusted() ? 'trusted' : 'anonymized';
    }
}
