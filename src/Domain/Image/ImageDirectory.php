<?php

declare(strict_types=1);

namespace App\Domain\Image;

enum ImageDirectory: string
{
    case GEAR = 'gear';
    case GEAR_MAINTENANCE = 'gear-maintenance';
    case ACTIVITIES = 'activities';
    case ACTIVITY_COLLECTIONS = 'activity-collections';
}
