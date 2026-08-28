<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

enum DrivetrainPosition: string
{
    case FRONT = 'front';
    case REAR = 'rear';
}
