<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use App\Infrastructure\Cache\CacheContext;
use App\Infrastructure\Time\Clock\Clock;

final readonly class CurrentDayCacheContext implements CacheContext
{
    public function __construct(
        private Clock $clock,
    ) {
    }

    public static function getKey(): string
    {
        return 'day';
    }

    public function resolve(): string
    {
        return $this->clock->getCurrentDateTimeImmutable()->format('Y-m-d');
    }
}
