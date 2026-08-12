<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Render;

enum CacheStatus: string
{
    case HIT = 'HIT';
    case MISS = 'MISS';
}
