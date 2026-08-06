<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;

final readonly class BestEfforts
{
    /**
     * @param array<string, array<string, array<int, ActivityBestEffort>>> $bestEffortPerPeriod
     * @param array<string, array<int, list<ActivityBestEffort>>>          $historyPerSportType
     * @param BestEffortPeriod[]                                           $periods
     * @param array<string, array<string, SportTypes>>                     $sportTypesPerPeriod
     */
    private function __construct(
        private array $bestEffortPerPeriod,
        private array $historyPerSportType,
        private array $periods,
        private array $sportTypesPerPeriod,
        private ActivityTypes $activityTypes,
    ) {
    }

    /**
     * @param array<string, array<string, array<int, ActivityBestEffort>>> $bestEffortPerPeriod
     * @param array<string, array<int, list<ActivityBestEffort>>>          $historyPerSportType
     * @param BestEffortPeriod[]                                           $periods
     * @param array<string, array<string, SportTypes>>                     $sportTypesPerPeriod
     */
    public static function create(
        array $bestEffortPerPeriod,
        array $historyPerSportType,
        array $periods,
        array $sportTypesPerPeriod,
        ActivityTypes $activityTypes,
    ): self {
        return new self(
            bestEffortPerPeriod: $bestEffortPerPeriod,
            historyPerSportType: $historyPerSportType,
            periods: $periods,
            sportTypesPerPeriod: $sportTypesPerPeriod,
            activityTypes: $activityTypes,
        );
    }

    public function for(BestEffortPeriod $period, SportType $sportType, ConvertableToMeter $distance): ?ActivityBestEffort
    {
        return $this->bestEffortPerPeriod[$period->value][$sportType->value][$distance->toMeter()->toInt()] ?? null;
    }

    public function historyFor(SportType $sportType, ConvertableToMeter $distance, int $position): ?ActivityBestEffort
    {
        return $this->historyPerSportType[$sportType->value][$distance->toMeter()->toInt()][$position] ?? null;
    }

    /**
     * @return BestEffortPeriod[]
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    public function getSportTypesFor(BestEffortPeriod $period, ActivityType $activityType): SportTypes
    {
        return $this->sportTypesPerPeriod[$period->value][$activityType->value] ?? SportTypes::empty();
    }

    public function getActivityTypes(): ActivityTypes
    {
        return $this->activityTypes;
    }
}
