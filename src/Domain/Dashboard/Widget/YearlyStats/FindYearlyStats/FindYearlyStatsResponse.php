<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStats;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;

readonly class FindYearlyStatsResponse implements Response
{
    public function __construct(
        /** @var array<int, array{'year': Year, 'activityType': ActivityType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> */
        private array $yearlyStats,
    ) {
    }

    /**
     * @return array{'year': Year, 'activityType': ActivityType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}|null
     */
    public function getFor(Year $year, ActivityType $activityType): ?array
    {
        foreach ($this->yearlyStats as $yearlyStat) {
            if ($year->toInt() !== $yearlyStat['year']->toInt()) {
                continue;
            }
            if ($activityType !== $yearlyStat['activityType']) {
                continue;
            }

            return $yearlyStat;
        }

        return null;
    }

    public function getUniqueYearsFor(ActivityType $activityType): Years
    {
        $years = array_map(
            static fn (array $yearlyStat): int => $yearlyStat['year']->toInt(),
            array_filter(
                $this->yearlyStats,
                static fn (array $yearlyStat): bool => $activityType === $yearlyStat['activityType'],
            ),
        );
        rsort($years);

        return Years::fromArray(array_map(Year::fromInt(...), $years));
    }
}
