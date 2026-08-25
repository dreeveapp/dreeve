<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

enum ActionType: string
{
    case ASSIGN_GEAR = 'assignGear';
    case SET_DEVICE = 'setDevice';
    case MARK_AS_COMMUTE = 'markAsCommute';
    case SET_SPORT_TYPE = 'setSportType';
    case SET_WORKOUT_TYPE = 'setWorkoutType';
    case SET_NAME = 'setName';
    case SET_DESCRIPTION = 'setDescription';
    case CALCULATE_KILOJOULES = 'calculateKilojoules';
}
