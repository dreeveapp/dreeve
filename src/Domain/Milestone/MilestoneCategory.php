<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

enum MilestoneCategory: string
{
    case FIRST_ACTIVITY_OF_SPORT_TYPE = 'first';
    case FIRST_ACTIVITY_IN_COUNTRY = 'firstActivityInCountry';
    case CUMULATIVE_DISTANCE = 'cumulativeDistance';
    case CUMULATIVE_ELEVATION = 'cumulativeElevation';
    case CUMULATIVE_MOVING_TIME = 'cumulativeMovingTime';
    case ACTIVITY_DISTANCE = 'activityDistance';
    case ACTIVITY_ELEVATION = 'activityElevation';
    case ACTIVITY_MOVING_TIME = 'activityMovingTime';
    case ACTIVITY_COUNT = 'activityCount';
    case PERSONAL_BEST = 'personalBest';
    case EDDINGTON = 'eddington';
    case STREAK = 'streak';
    case GEAR_DISTANCE = 'gearDistance';
    case GEAR_ELEVATION = 'gearElevation';
    case GEAR_MOVING_TIME = 'gearMovingTime';
    case EXPLORER_TILES_MAX_SQUARE = 'explorerTilesMaxSquare';
    case EXPLORER_TILES_MAX_CLUSTER = 'explorerTilesMaxCluster';

    public function getFilterGroup(): MilestoneFilterGroup
    {
        return match ($this) {
            self::FIRST_ACTIVITY_OF_SPORT_TYPE, self::FIRST_ACTIVITY_IN_COUNTRY => MilestoneFilterGroup::FIRST,
            self::CUMULATIVE_DISTANCE, self::ACTIVITY_DISTANCE => MilestoneFilterGroup::DISTANCE,
            self::CUMULATIVE_ELEVATION, self::ACTIVITY_ELEVATION => MilestoneFilterGroup::ELEVATION,
            self::CUMULATIVE_MOVING_TIME, self::ACTIVITY_MOVING_TIME => MilestoneFilterGroup::MOVING_TIME,
            self::ACTIVITY_COUNT => MilestoneFilterGroup::ACTIVITY,
            self::PERSONAL_BEST => MilestoneFilterGroup::PERSONAL_BEST,
            self::EDDINGTON => MilestoneFilterGroup::EDDINGTON,
            self::STREAK => MilestoneFilterGroup::STREAK,
            self::GEAR_DISTANCE, self::GEAR_ELEVATION, self::GEAR_MOVING_TIME => MilestoneFilterGroup::GEAR,
            self::EXPLORER_TILES_MAX_SQUARE, self::EXPLORER_TILES_MAX_CLUSTER => MilestoneFilterGroup::EXPLORER_TILES,
        };
    }
}
