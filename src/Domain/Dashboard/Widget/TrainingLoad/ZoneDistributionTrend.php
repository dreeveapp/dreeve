<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ZoneDistributionTrend implements TranslatableInterface
{
    case UP;
    case DOWN;
    case STEADY;

    public static function fromPercentages(float $current, float $previous): self
    {
        return match (true) {
            $current > $previous => self::UP,
            $current < $previous => self::DOWN,
            default => self::STEADY,
        };
    }

    public function getSvgIcon(): ?string
    {
        return match ($this) {
            self::UP => 'trend-up',
            self::DOWN => 'trend-down',
            self::STEADY => null,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::UP => $translator->trans('Increased compared to yesterday', locale: $locale),
            self::DOWN => $translator->trans('Decreased compared to yesterday', locale: $locale),
            self::STEADY => $translator->trans('Unchanged compared to yesterday', locale: $locale),
        };
    }
}
