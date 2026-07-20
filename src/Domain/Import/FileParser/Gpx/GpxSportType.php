<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Gpx;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\SportTypeName;

final class GpxSportType
{
    /**
     * Strava GPX exports write a numeric activity type id in <type>. Only
     * community-confirmed codes are mapped; unknown codes fall back to a workout.
     *
     * @var array<int, SportType>
     */
    private const array STRAVA_NUMERIC_TYPES = [
        1 => SportType::RIDE,
        4 => SportType::HIKE,
        9 => SportType::RUN,
        10 => SportType::WALK,
    ];

    public static function resolve(string $gpxType): SportType
    {
        if (ctype_digit($gpxType)) {
            return self::STRAVA_NUMERIC_TYPES[(int) $gpxType] ?? SportType::WORKOUT;
        }

        return SportTypeName::tryResolve($gpxType) ?? SportType::WORKOUT;
    }
}
