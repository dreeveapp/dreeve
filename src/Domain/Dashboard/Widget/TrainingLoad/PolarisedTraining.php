<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZonesForRollingWindow;

final readonly class PolarisedTraining
{
    private const int RENDERED_PRECISION = 2;

    /**
     * @param array<int, PolarisedTrainingZoneShare> $shares
     */
    private function __construct(
        private array $shares,
    ) {
    }

    public static function fromRollingWindow(TimeInHeartRateZonesForRollingWindow $rollingWindow): self
    {
        $current = $rollingWindow->getCurrent();
        $asOfPreviousDay = $rollingWindow->getAsOfPreviousDay();
        $windowsAreComparable = $current->getTotalTimeInSeconds() > 0
            && $asOfPreviousDay->getTotalTimeInSeconds() > 0;

        $shares = [];
        foreach (PolarisedTrainingZone::cases() as $zone) {
            $percentage = round($zone->getPercentageIn($current), self::RENDERED_PRECISION);

            $shares[] = PolarisedTrainingZoneShare::create(
                zone: $zone,
                percentage: $percentage,
                trend: $windowsAreComparable ? ZoneDistributionTrend::fromPercentages(
                    current: $percentage,
                    previous: round($zone->getPercentageIn($asOfPreviousDay), self::RENDERED_PRECISION),
                ) : ZoneDistributionTrend::STEADY,
            );
        }

        return new self($shares);
    }

    /**
     * @return array<int, PolarisedTrainingZoneShare>
     */
    public function getShares(): array
    {
        return $this->shares;
    }
}
