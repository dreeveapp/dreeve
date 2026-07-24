<?php

declare(strict_types=1);

namespace App\Domain\Calendar\FindMonthlyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\Month;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindMonthlyStatsResponse implements Response
{
    public function __construct(
        /** @var array<int, array{'month': Month, 'sportType': SportType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> */
        private array $statsPerMonth,
        /** @var array<int, array{'activityType': ActivityType, min: Month, max: Month}> */
        private array $minMaxMonthPerActivityType,
    ) {
    }

    public function getFirstMonthFor(ActivityType $activityType): Month
    {
        foreach ($this->minMaxMonthPerActivityType as $minMaxDatePerActivityType) {
            if ($minMaxDatePerActivityType['activityType'] !== $activityType) {
                continue;
            }

            return $minMaxDatePerActivityType['min'];
        }

        throw new \RuntimeException('No min date found for activity type '.$activityType->value);
    }

    public function getLastMonthFor(ActivityType $activityType): Month
    {
        foreach ($this->minMaxMonthPerActivityType as $minMaxDatePerActivityType) {
            if ($minMaxDatePerActivityType['activityType'] !== $activityType) {
                continue;
            }

            return $minMaxDatePerActivityType['max'];
        }

        throw new \RuntimeException('No max date found for activity type '.$activityType->value);
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    public function getTotals(): array
    {
        return $this->aggregateStats($this->statsPerMonth);
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}|null
     */
    public function getForMonth(Month $month): ?array
    {
        $stats = array_filter(
            $this->statsPerMonth,
            fn (array $entry): bool => $entry['month']->getId() === $month->getId()
        );
        $result = $this->aggregateStats($stats);

        return 0 === $result['numberOfActivities'] ? null : $result;
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    public function getForMonthAndSportType(Month $month, SportType $sportType): array
    {
        $stats = array_filter(
            $this->statsPerMonth,
            fn (array $entry): bool => $entry['month']->getId() === $month->getId() && $entry['sportType'] === $sportType
        );

        return $this->aggregateStats($stats);
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}|null
     */
    public function getForMonthAndActivityType(Month $month, ActivityType $activityType): ?array
    {
        $sportTypes = $activityType->getSportTypes();

        $stats = array_filter(
            $this->statsPerMonth,
            fn (array $entry): bool => $entry['month']->getId() === $month->getId() && $sportTypes->has($entry['sportType'])
        );

        $result = $this->aggregateStats($stats);

        return 0 === $result['numberOfActivities'] ? null : $result;
    }

    /**
     * @param array<int, array{'month': Month, 'sportType': SportType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> $stats
     *
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    private function aggregateStats(array $stats): array
    {
        $totals = [
            'numberOfActivities' => 0,
            'distance' => 0.0,
            'elevation' => 0,
            'movingTime' => 0,
            'calories' => 0,
        ];

        foreach ($stats as $entry) {
            $totals['numberOfActivities'] += $entry['numberOfActivities'];
            $totals['distance'] += $entry['distance']->toFloat();
            $totals['elevation'] += $entry['elevation']->toInt();
            $totals['movingTime'] += $entry['movingTime']->toInt();
            $totals['calories'] += $entry['calories'];
        }

        return [
            'numberOfActivities' => $totals['numberOfActivities'],
            'distance' => Kilometer::from($totals['distance']),
            'elevation' => Meter::from($totals['elevation']),
            'movingTime' => Seconds::from($totals['movingTime']),
            'calories' => $totals['calories'],
        ];
    }
}
