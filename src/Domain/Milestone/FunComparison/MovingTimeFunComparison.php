<?php

declare(strict_types=1);

namespace App\Domain\Milestone\FunComparison;

use App\Infrastructure\Measurement\Time\Hour;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MovingTimeFunComparison: string implements FunComparison
{
    case FULL_DAY = 'timeFullDay';
    case TWO_FULL_DAYS = 'timeTwoFullDays';
    case FOUR_DAYS = 'timeFourDays';
    case FULL_WEEK = 'timeFullWeek';
    case TEN_DAYS = 'timeTenDays';
    case THREE_WEEKS = 'timeThreeWeeks';
    case FULL_MONTH = 'timeFullMonth';
    case FORTY_ONE_DAYS = 'timeFortyOneDays';
    case TWO_MONTHS = 'timeTwoMonths';
    case NEARLY_THREE_MONTHS = 'timeNearlyThreeMonths';
    case THREE_AND_HALF_MONTHS = 'timeThreeAndHalfMonths';
    case FOUR_MONTHS = 'timeFourMonths';
    case FIVE_AND_HALF_MONTHS = 'timeFiveAndHalfMonths';
    case SEVEN_MONTHS = 'timeSevenMonths';
    case TEN_MONTHS = 'timeTenMonths';
    case FULL_YEAR = 'timeFullYear';

    public static function resolve(Hour $movingTime): ?self
    {
        $hours = $movingTime->toFloat();

        return match (true) {
            $hours >= 10_000 => self::FULL_YEAR,
            $hours >= 7_500 => self::TEN_MONTHS,
            $hours >= 5_000 => self::SEVEN_MONTHS,
            $hours >= 4_000 => self::FIVE_AND_HALF_MONTHS,
            $hours >= 3_000 => self::FOUR_MONTHS,
            $hours >= 2_500 => self::THREE_AND_HALF_MONTHS,
            $hours >= 2_000 => self::NEARLY_THREE_MONTHS,
            $hours >= 1_500 => self::TWO_MONTHS,
            $hours >= 1_000 => self::FORTY_ONE_DAYS,
            $hours >= 750 => self::FULL_MONTH,
            $hours >= 500 => self::THREE_WEEKS,
            $hours >= 250 => self::TEN_DAYS,
            $hours >= 168 => self::FULL_WEEK,
            $hours >= 100 => self::FOUR_DAYS,
            $hours >= 48 => self::TWO_FULL_DAYS,
            $hours >= 24 => self::FULL_DAY,
            default => null,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::FULL_DAY => $translator->trans("That's a full day of non-stop movement", locale: $locale),
            self::TWO_FULL_DAYS => $translator->trans("That's 2 full days of non-stop activity", locale: $locale),
            self::FOUR_DAYS => $translator->trans("That's more than 4 days of continuous activity", locale: $locale),
            self::FULL_WEEK => $translator->trans("That's a full week of non-stop movement", locale: $locale),
            self::TEN_DAYS => $translator->trans("That's more than 10 days of non-stop movement", locale: $locale),
            self::THREE_WEEKS => $translator->trans("That's almost 21 days of continuous movement", locale: $locale),
            self::FULL_MONTH => $translator->trans("That's a full month of non-stop activity", locale: $locale),
            self::FORTY_ONE_DAYS => $translator->trans("That's more than 41 days of non-stop movement", locale: $locale),
            self::TWO_MONTHS => $translator->trans("That's 2 months without stopping", locale: $locale),
            self::NEARLY_THREE_MONTHS => $translator->trans("That's nearly 3 months of non-stop activity", locale: $locale),
            self::THREE_AND_HALF_MONTHS => $translator->trans("That's about 3.5 months of continuous movement", locale: $locale),
            self::FOUR_MONTHS => $translator->trans("That's more than 4 months of non-stop activity", locale: $locale),
            self::FIVE_AND_HALF_MONTHS => $translator->trans("That's over 5.5 months of continuous movement", locale: $locale),
            self::SEVEN_MONTHS => $translator->trans("That's nearly 7 months of non-stop activity", locale: $locale),
            self::TEN_MONTHS => $translator->trans("That's more than 10 months of continuous movement", locale: $locale),
            self::FULL_YEAR => $translator->trans("That's more than a full year of non-stop movement", locale: $locale),
        };
    }
}
