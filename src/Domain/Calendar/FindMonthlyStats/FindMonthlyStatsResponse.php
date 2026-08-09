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
        // Every lookup this response supports is resolved up front into a keyed map. The templates ask for
        // stats month by month and sport type by sport type, so filtering the full list on each call turned
        // rendering into a quadratic walk over thousands of rows.
        $totals = self::emptyRawTotals();
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

            $totals = self::addToRawTotals($totals, $entry);
            $perMonthId[$monthId] = self::addToRawTotals($perMonthId[$monthId] ?? self::emptyRawTotals(), $entry);
            $perMonthIdAndSportType[$monthId][$sportTypeValue] = self::addToRawTotals(
                $perMonthIdAndSportType[$monthId][$sportTypeValue] ?? self::emptyRawTotals(),
                $entry
            );
            $perMonthIdAndActivityType[$monthId][$activityTypeValue] = self::addToRawTotals(
                $perMonthIdAndActivityType[$monthId][$activityTypeValue] ?? self::emptyRawTotals(),
                $entry
            );
        }

        $this->totals = self::toMeasuredTotals($totals);
        $this->statsPerMonthId = array_map(self::toMeasuredTotals(...), $perMonthId);
        $this->statsPerMonthIdAndSportType = array_map(
            fn (array $perSportType): array => array_map(self::toMeasuredTotals(...), $perSportType),
            $perMonthIdAndSportType
        );
        $this->statsPerMonthIdAndActivityType = array_map(
            fn (array $perActivityType): array => array_map(self::toMeasuredTotals(...), $perActivityType),
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

    /**
     * The month the very first activity took place in, regardless of sport type. Null when there are no
     * activities at all.
     */
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
            ?? self::toMeasuredTotals(self::emptyRawTotals());
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
    private static function emptyRawTotals(): array
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
    private static function addToRawTotals(array $totals, array $entry): array
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
    private static function toMeasuredTotals(array $totals): array
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
