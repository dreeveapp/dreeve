<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityExclusion: string implements TranslatableInterface
{
    case PEAK_POWER_OUTPUTS = 'peakPowerOutputs';
    case BEST_EFFORTS = 'bestEfforts';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::PEAK_POWER_OUTPUTS => $translator->trans('Peak power outputs', locale: $locale),
            self::BEST_EFFORTS => $translator->trans('Best efforts', locale: $locale),
        };
    }
}
