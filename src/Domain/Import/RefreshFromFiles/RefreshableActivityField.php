<?php

declare(strict_types=1);

namespace App\Domain\Import\RefreshFromFiles;

enum RefreshableActivityField: string
{
    case DESCENT = 'descent';
    case WORKOUT_RPE = 'workoutRpe';
    case WORKOUT_FEEL = 'workoutFeel';
}
