<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Weekday: int implements TranslatableInterface
{
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 0;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::MONDAY => $translator->trans('Mon.', locale: $locale),
            self::TUESDAY => $translator->trans('Tue.', locale: $locale),
            self::WEDNESDAY => $translator->trans('Wed.', locale: $locale),
            self::THURSDAY => $translator->trans('Thu.', locale: $locale),
            self::FRIDAY => $translator->trans('Fri.', locale: $locale),
            self::SATURDAY => $translator->trans('Sat.', locale: $locale),
            self::SUNDAY => $translator->trans('Sun.', locale: $locale),
        };
    }
}
