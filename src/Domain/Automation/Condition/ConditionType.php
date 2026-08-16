<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

enum ConditionType: string
{
    case NAME = 'name';
    case DEVICE = 'device';
    case CONNECTED_SENSORS = 'connectedSensors';
    case SPORT_TYPE = 'sportType';
    case DISTANCE = 'distance';
    case ELEVATION = 'elevation';
    case AVERAGE_POWER = 'averagePower';
    case AVERAGE_CADENCE = 'averageCadence';
    case WEEKDAY = 'weekday';
    case TIME_OF_DAY = 'timeOfDay';
    case STARTS_NEAR = 'startsNear';
    case ENDS_NEAR = 'endsNear';
    case PASSES_NEAR = 'passesNear';
}
