<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum PolarisedTrainingZone implements TranslatableInterface
{
    case LOW;
    case MODERATE;
    case HIGH;

    public function getPercentageIn(TimeInHeartRateZones $timeInHeartRateZones): float
    {
        return match ($this) {
            self::LOW => $timeInHeartRateZones->getPercentageInLowZones(),
            self::MODERATE => $timeInHeartRateZones->getPercentageInMediumZone(),
            self::HIGH => $timeInHeartRateZones->getPercentageInHighZones(),
        };
    }

    public function isWithinRecommendedRange(float $percentage): bool
    {
        [$lowerBound, $upperBound] = $this->getRecommendedRangeBounds();

        return $percentage >= $lowerBound && $percentage <= $upperBound;
    }

    public function getRecommendedRange(): string
    {
        [$lowerBound, $upperBound] = $this->getRecommendedRangeBounds();

        return sprintf('%d - %d%%', $lowerBound, $upperBound);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::LOW => $translator->trans('Z1-2 (Low)', locale: $locale),
            self::MODERATE => $translator->trans('Z3 (Mod)', locale: $locale),
            self::HIGH => $translator->trans('Z4-5 (High)', locale: $locale),
        };
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function getRecommendedRangeBounds(): array
    {
        return match ($this) {
            self::LOW => [75.0, 90.0],
            self::MODERATE => [0.0, 10.0],
            self::HIGH => [10.0, 20.0],
        };
    }
}
