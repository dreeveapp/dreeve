<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum DropZoneItemStatus: string
{
    case NEW = 'new';
    case UNCHANGED = 'unchanged';
    case REMOVED = 'removed';
}
