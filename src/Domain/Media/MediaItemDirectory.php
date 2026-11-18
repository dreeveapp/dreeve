<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum MediaItemDirectory: string
{
    case ACTIVITIES = 'activities';
    case ACTIVITY_COLLECTIONS = 'activity-collections';
}
