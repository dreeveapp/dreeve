<?php

declare(strict_types=1);

namespace App\Domain\Activity\Scan;

enum ActivityScanType: string
{
    case CUSTOM_SEGMENT = 'customSegment';
}
