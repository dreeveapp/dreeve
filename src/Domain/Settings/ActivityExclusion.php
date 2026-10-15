<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityExclusion: string implements TranslatableInterface
{
    case PEAK_POWER_OUTPUTS = 'peakPowerOutputs';

    public function getSettingsName(): SettingsName
    {
        return match ($this) {
            self::PEAK_POWER_OUTPUTS => SettingsName::ACTIVITIES_EXCLUDED_FROM_PEAK_POWER_OUTPUTS,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::PEAK_POWER_OUTPUTS => $translator->trans('Peak power outputs', locale: $locale),
        };
    }
}
