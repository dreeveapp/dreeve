<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingGoals;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TrainingGoalType: string implements TranslatableInterface
{
    case DISTANCE = 'distance';
    case ELEVATION = 'elevation';
    case MOVING_TIME = 'movingTime';
    case NUMBER_OF_ACTIVITIES = 'numberOfActivities';
    case CALORIES = 'calories';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DISTANCE => $translator->trans('Distance', locale: $locale),
            self::ELEVATION => $translator->trans('Elevation', locale: $locale),
            self::MOVING_TIME => $translator->trans('Moving time', locale: $locale),
            self::NUMBER_OF_ACTIVITIES => $translator->trans('Number of activities', locale: $locale),
            self::CALORIES => $translator->trans('Calories', locale: $locale),
        };
    }

    /**
     * @return TrainingGoalType[]
     */
    public static function lengthRelated(): array
    {
        return [self::DISTANCE, self::ELEVATION];
    }

    /**
     * @return TrainingGoalType[]
     */
    public static function simpleUnitRelated(): array
    {
        return [self::NUMBER_OF_ACTIVITIES, self::CALORIES];
    }

    public function getSvgIcon(): string
    {
        return match ($this) {
            self::MOVING_TIME => 'time',
            self::DISTANCE => 'distance',
            self::ELEVATION => 'elevation',
            self::CALORIES => 'calories',
            self::NUMBER_OF_ACTIVITIES => 'hashtag',
        };
    }
}
