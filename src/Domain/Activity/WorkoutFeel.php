<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WorkoutFeel: int implements TranslatableInterface
{
    case VERY_WEAK = 0;
    case WEAK = 25;
    case NORMAL = 50;
    case STRONG = 75;
    case VERY_STRONG = 100;

    public static function fromRaw(int $value): self
    {
        return self::from((int) (round(max(0, min(100, $value)) / 25) * 25));
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::VERY_WEAK => $translator->trans('Very weak', locale: $locale),
            self::WEAK => $translator->trans('Weak', locale: $locale),
            self::NORMAL => $translator->trans('Normal', locale: $locale),
            self::STRONG => $translator->trans('Strong', locale: $locale),
            self::VERY_STRONG => $translator->trans('Very strong', locale: $locale),
        };
    }
}
