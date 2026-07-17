<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WeekDay: int implements TranslatableInterface
{
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 7;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::MONDAY => $translator->trans('Monday', domain: 'admin', locale: $locale),
            self::TUESDAY => $translator->trans('Tuesday', domain: 'admin', locale: $locale),
            self::WEDNESDAY => $translator->trans('Wednesday', domain: 'admin', locale: $locale),
            self::THURSDAY => $translator->trans('Thursday', domain: 'admin', locale: $locale),
            self::FRIDAY => $translator->trans('Friday', domain: 'admin', locale: $locale),
            self::SATURDAY => $translator->trans('Saturday', domain: 'admin', locale: $locale),
            self::SUNDAY => $translator->trans('Sunday', domain: 'admin', locale: $locale),
        };
    }
}
