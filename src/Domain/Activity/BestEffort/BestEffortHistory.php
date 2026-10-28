<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;

final readonly class BestEffortHistory
{
    /**
     * @param array<string, list<ActivityBestEffort>> $topPerSportType
     */
    private function __construct(
        private SportTypes $sportTypes,
        private array $topPerSportType,
    ) {
    }

    /**
     * @param array<string, list<ActivityBestEffort>> $topPerSportType
     */
    public static function create(
        SportTypes $sportTypes,
        array $topPerSportType,
    ): self {
        return new self(
            sportTypes: $sportTypes,
            topPerSportType: $topPerSportType,
        );
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }

    /**
     * @return list<ActivityBestEffort>
     */
    public function getTopFor(SportType $sportType): array
    {
        return $this->topPerSportType[$sportType->value] ?? [];
    }

    public function getActivityIds(): ActivityIds
    {
        $activityIds = ActivityIds::empty();
        foreach ($this->topPerSportType as $bestEfforts) {
            foreach ($bestEfforts as $bestEffort) {
                $activityIds->add($bestEffort->getActivityId());
            }
        }

        return $activityIds->unique();
    }
}
