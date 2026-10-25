<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Daytime: string implements TranslatableInterface
{
    case MORNING = 'Morning';
    case AFTERNOON = 'Afternoon';
    case EVENING = 'Evening';
    case NIGHT = 'Night';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::MORNING => $translator->trans('Morning', locale: $locale),
            self::AFTERNOON => $translator->trans('Afternoon', locale: $locale),
            self::EVENING => $translator->trans('Evening', locale: $locale),
            self::NIGHT => $translator->trans('Night', locale: $locale),
        };
    }

    public function getSvgIcon(): string
    {
        return match ($this) {
            self::MORNING => 'sunrise',
            self::AFTERNOON => 'sun',
            self::EVENING => 'sunset',
            self::NIGHT => 'moon',
        };
    }

    public function getStartHourConfigKey(): string
    {
        return match ($this) {
            self::MORNING => 'morningStartHour',
            self::AFTERNOON => 'afternoonStartHour',
            self::EVENING => 'eveningStartHour',
            self::NIGHT => 'nightStartHour',
        };
    }
}
