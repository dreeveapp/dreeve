<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

enum CacheTag: string
{
    case ACTIVITIES = 'activities';
    case SETTINGS = 'settings';
}
