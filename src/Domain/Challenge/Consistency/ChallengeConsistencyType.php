<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ChallengeConsistencyType: string implements TranslatableInterface
{
    case DISTANCE = 'distance';
    case DISTANCE_IN_ONE_ACTIVITY = 'distanceInOneActivity';
    case ELEVATION = 'elevation';
    case ELEVATION_IN_ONE_ACTIVITY = 'elevationInOneActivity';
    case MOVING_TIME = 'movingTime';
    case NUMBER_OF_ACTIVITIES = 'numberOfActivities';
    case CALORIES = 'calories';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DISTANCE => $translator->trans('Distance', locale: $locale),
            self::DISTANCE_IN_ONE_ACTIVITY => $translator->trans('Distance (single activity)', locale: $locale),
            self::ELEVATION => $translator->trans('Elevation', locale: $locale),
            self::ELEVATION_IN_ONE_ACTIVITY => $translator->trans('Elevation (single activity)', locale: $locale),
            self::MOVING_TIME => $translator->trans('Moving time', locale: $locale),
            self::NUMBER_OF_ACTIVITIES => $translator->trans('Number of activities', locale: $locale),
            self::CALORIES => $translator->trans('Calories', locale: $locale),
        };
    }

    /**
     * @return ChallengeConsistencyType[]
     */
    public static function lengthRelated(): array
    {
        return [self::DISTANCE, self::DISTANCE_IN_ONE_ACTIVITY, self::ELEVATION, self::ELEVATION_IN_ONE_ACTIVITY];
    }
}
