<?php

declare(strict_types=1);

namespace App\Domain\Activity\Comparison;

use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ComparisonMetric: string implements TranslatableInterface
{
    case MOVING_TIME = 'movingTime';
    case AVERAGE_SPEED = 'averageSpeed';
    case NORMALIZED_POWER = 'normalizedPower';
    case AVERAGE_POWER = 'averagePower';
    case AVERAGE_HEART_RATE = 'averageHeartRate';
    case CALORIES = 'calories';
    case KILOJOULES = 'kilojoules';
    case ELEVATION = 'elevation';
    case SPEED_TO_POWER_RATIO = 'speedToPowerRatio';
    case EFFICIENCY_FACTOR = 'efficiencyFactor';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::MOVING_TIME => $translator->trans('Duration', locale: $locale),
            self::AVERAGE_SPEED => $translator->trans('Avg speed', locale: $locale),
            self::NORMALIZED_POWER => $translator->trans('Normalized power', locale: $locale),
            self::AVERAGE_POWER => $translator->trans('Avg power', locale: $locale),
            self::AVERAGE_HEART_RATE => $translator->trans('Avg heart rate', locale: $locale),
            self::CALORIES => $translator->trans('Calories', locale: $locale),
            self::KILOJOULES => $translator->trans('Work', locale: $locale),
            self::ELEVATION => $translator->trans('Elevation', locale: $locale),
            self::SPEED_TO_POWER_RATIO => $translator->trans('Speed / power', locale: $locale),
            self::EFFICIENCY_FACTOR => $translator->trans('Efficiency factor', locale: $locale),
        };
    }

    public function getDirection(): ComparisonMetricDirection
    {
        return match ($this) {
            self::MOVING_TIME => ComparisonMetricDirection::LOWER_IS_BETTER,
            self::AVERAGE_SPEED, self::NORMALIZED_POWER, self::AVERAGE_POWER, self::EFFICIENCY_FACTOR, self::SPEED_TO_POWER_RATIO => ComparisonMetricDirection::HIGHER_IS_BETTER,
            self::AVERAGE_HEART_RATE, self::CALORIES, self::KILOJOULES, self::ELEVATION => ComparisonMetricDirection::NEUTRAL,
        };
    }

    public function getUnitSymbol(UnitSystem $unitSystem): string
    {
        return match ($this) {
            self::MOVING_TIME, self::EFFICIENCY_FACTOR => '',
            self::AVERAGE_SPEED => $unitSystem->speed(1)->getSymbol(),
            self::NORMALIZED_POWER, self::AVERAGE_POWER => 'W',
            self::AVERAGE_HEART_RATE => 'bpm',
            self::CALORIES => 'kcal',
            self::KILOJOULES => 'kJ',
            self::ELEVATION => $unitSystem->elevationSymbol(),
            self::SPEED_TO_POWER_RATIO => sprintf('(%s)/W', $unitSystem->speed(1)->getSymbol()),
        };
    }

    public function getChartValueFormatter(): ?string
    {
        return match ($this) {
            self::MOVING_TIME => 'callback:formatSecondsTrimZero',
            self::ELEVATION => 'callback:formatElevation',
            self::NORMALIZED_POWER, self::AVERAGE_POWER, self::AVERAGE_HEART_RATE, self::CALORIES, self::KILOJOULES => 'callback:toInteger',
            self::AVERAGE_SPEED, self::SPEED_TO_POWER_RATIO, self::EFFICIENCY_FACTOR => null,
        };
    }
}
