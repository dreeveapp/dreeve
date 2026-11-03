<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

enum ActivityCollectionType: string
{
    case OTHER = 'other';
    case TRIP = 'trip';
    case EVENT = 'event';
}
