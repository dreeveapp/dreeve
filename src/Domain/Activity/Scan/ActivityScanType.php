<?php

declare(strict_types=1);

namespace App\Domain\Activity\Scan;

enum ActivityScanType: string
{
    case STREAMS = 'streams';
    case CUSTOM_SEGMENT = 'customSegment';
}
