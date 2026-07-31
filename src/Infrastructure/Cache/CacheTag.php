<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

enum CacheTag: string
{
    case ACTIVITIES = 'activities';
    case GEAR = 'gear';
    case SEGMENTS = 'segments';
    case CHALLENGES = 'challenges';
    case SETTINGS = 'settings';
    case DASHBOARD = 'dashboard';
}
