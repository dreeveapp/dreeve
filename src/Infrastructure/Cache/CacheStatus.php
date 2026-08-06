<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

enum CacheStatus: string
{
    case HIT = 'HIT';
    case MISS = 'MISS';
    case UNCACHEABLE = 'UNCACHEABLE';
}
