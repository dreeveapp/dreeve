<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

enum GearPosition: string
{
    case FRONT = 'front';
    case REAR = 'rear';
    case NONE = 'none';
}
