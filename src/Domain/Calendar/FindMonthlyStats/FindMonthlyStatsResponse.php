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
    /** @var array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int} */
    private array $totals;
    /** @var array<string, array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> */
    private array $statsPerMonthId;
    /** @var array<string, array<string, array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}>> */
    private array $statsPerMonthIdAndSportType;
    /** @var array<string, array<string, array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}>> */
    private array $statsPerMonthIdAndActivityType;
    private ?Month $firstMonth;
    /** @var array<string, array{min: Month, max: Month}> */
    private array $minMaxMonthPerActivityTypeValue;

    /**
     * @param array<int, array{'month': Month, 'sportType': SportType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> $statsPerMonth
     * @param array<int, array{'activityType': ActivityType, min: Month, max: Month}>                                                                                                 $minMaxMonthPerActivityType
     */
    public function __construct(
        array $statsPerMonth,
        array $minMaxMonthPerActivityType,
    ) {
        $totals = $this->emptyRawTotals();
        $perMonthId = [];
        $perMonthIdAndSportType = [];
        $perMonthIdAndActivityType = [];
        $firstMonth = null;
        $months = [];

        foreach ($statsPerMonth as $entry) {
            $month = $entry['month'];
            $monthId = $month->getId();
            $sportTypeValue = $entry['sportType']->value;
            $activityTypeValue = $entry['sportType']->getActivityType()->value;

            $months[$monthId] = $month;
            if (!$firstMonth instanceof Month || $month->isBefore($firstMonth)) {
                $firstMonth = $month;
            }

            $totals = $this->addToRawTotals($totals, $entry);
            $perMonthId[$monthId] = $this->addToRawTotals($perMonthId[$monthId] ?? $this->emptyRawTotals(), $entry);
            $perMonthIdAndSportType[$monthId][$sportTypeValue] = $this->addToRawTotals($perMonthIdAndSportType[$monthId][$sportTypeValue] ?? $this->emptyRawTotals(), $entry);
            $perMonthIdAndActivityType[$monthId][$activityTypeValue] = $this->addToRawTotals($perMonthIdAndActivityType[$monthId][$activityTypeValue] ?? $this->emptyRawTotals(), $entry);
        }

        $this->totals = $this->toMeasuredTotals($totals);
        $this->statsPerMonthId = array_map($this->toMeasuredTotals(...), $perMonthId);
        $this->statsPerMonthIdAndSportType = array_map(
            fn (array $perSportType): array => array_map($this->toMeasuredTotals(...), $perSportType),
            $perMonthIdAndSportType
        );
        $this->statsPerMonthIdAndActivityType = array_map(
            fn (array $perActivityType): array => array_map($this->toMeasuredTotals(...), $perActivityType),
            $perMonthIdAndActivityType
        );
        $this->firstMonth = $firstMonth;

        $minMaxMonthPerActivityTypeValue = [];
        foreach ($minMaxMonthPerActivityType as $minMax) {
            $minMaxMonthPerActivityTypeValue[$minMax['activityType']->value] = [
                'min' => $minMax['min'],
                'max' => $minMax['max'],
            ];
        }
        $this->minMaxMonthPerActivityTypeValue = $minMaxMonthPerActivityTypeValue;
    }

    public function getFirstMonthFor(ActivityType $activityType): Month
    {
        return $this->minMaxMonthPerActivityTypeValue[$activityType->value]['min']
            ?? throw new \RuntimeException('No min date found for activity type '.$activityType->value);
    }

    public function getLastMonthFor(ActivityType $activityType): Month
    {
        return $this->minMaxMonthPerActivityTypeValue[$activityType->value]['max']
            ?? throw new \RuntimeException('No max date found for activity type '.$activityType->value);
    }

    public function getFirstMonth(): ?Month
    {
        return $this->firstMonth;
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    public function getTotals(): array
    {
        return $this->totals;
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}|null
     */
    public function getForMonth(Month $month): ?array
    {
        return $this->statsPerMonthId[$month->getId()] ?? null;
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    public function getForMonthAndSportType(Month $month, SportType $sportType): array
    {
        return $this->statsPerMonthIdAndSportType[$month->getId()][$sportType->value]
            ?? $this->toMeasuredTotals($this->emptyRawTotals());
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}|null
     */
    public function getForMonthAndActivityType(Month $month, ActivityType $activityType): ?array
    {
        return $this->statsPerMonthIdAndActivityType[$month->getId()][$activityType->value] ?? null;
    }

    /**
     * @return array{'numberOfActivities': int, 'distance': float, 'elevation': int, 'movingTime': int, 'calories': int}
     */
    private function emptyRawTotals(): array
    {
        return [
            'numberOfActivities' => 0,
            'distance' => 0.0,
            'elevation' => 0,
            'movingTime' => 0,
            'calories' => 0,
        ];
    }

    /**
     * @param array{'numberOfActivities': int, 'distance': float, 'elevation': int, 'movingTime': int, 'calories': int}                                                   $totals
     * @param array{'month': Month, 'sportType': SportType, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int} $entry
     *
     * @return array{'numberOfActivities': int, 'distance': float, 'elevation': int, 'movingTime': int, 'calories': int}
     */
    private function addToRawTotals(array $totals, array $entry): array
    {
        return [
            'numberOfActivities' => $totals['numberOfActivities'] + $entry['numberOfActivities'],
            'distance' => $totals['distance'] + $entry['distance']->toFloat(),
            'elevation' => $totals['elevation'] + $entry['elevation']->toInt(),
            'movingTime' => $totals['movingTime'] + $entry['movingTime']->toInt(),
            'calories' => $totals['calories'] + $entry['calories'],
        ];
    }

    /**
     * @param array{'numberOfActivities': int, 'distance': float, 'elevation': int, 'movingTime': int, 'calories': int} $totals
     *
     * @return array{'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}
     */
    private function toMeasuredTotals(array $totals): array
    {
        return [
            'numberOfActivities' => $totals['numberOfActivities'],
            'distance' => Kilometer::from($totals['distance']),
            'elevation' => Meter::from($totals['elevation']),
            'movingTime' => Seconds::from($totals['movingTime']),
            'calories' => $totals['calories'],
        ];
    }
}
