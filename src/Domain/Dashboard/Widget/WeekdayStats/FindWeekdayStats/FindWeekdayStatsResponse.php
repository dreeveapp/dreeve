<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\Widget\WeekdayStats\Weekday;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindWeekdayStatsResponse implements Response
{
    /**
     * @param array<int, array{activityType: ActivityType, weekday: Weekday, numberOfActivities: int, distance: Kilometer, elevation: Meter, movingTime: Seconds}> $statsPerWeekday
     */
    public function __construct(
        private array $statsPerWeekday,
    ) {
    }

    /**
     * @return array<int, array{weekday: Weekday, numberOfActivities: int, distance: Kilometer, averageDistance: Kilometer, elevation: Meter, movingTime: Seconds, percentage: float|int}>
     */
    public function getStatsPerWeekday(?ActivityType $activityType): array
    {
        $totals = [];
        foreach (Weekday::cases() as $weekday) {
            $totals[$weekday->value] = [
                'numberOfActivities' => 0,
                'distance' => 0.0,
                'elevation' => 0.0,
                'movingTime' => 0,
            ];
        }

        foreach ($this->statsPerWeekday as $stats) {
            if ($activityType instanceof ActivityType && $stats['activityType'] !== $activityType) {
                continue;
            }
            $weekday = $stats['weekday']->value;
            $totals[$weekday]['numberOfActivities'] += $stats['numberOfActivities'];
            $totals[$weekday]['distance'] += $stats['distance']->toFloat();
            $totals[$weekday]['elevation'] += $stats['elevation']->toFloat();
            $totals[$weekday]['movingTime'] += $stats['movingTime']->toInt();
        }

        $totalMovingTime = array_sum(array_column($totals, 'movingTime'));

        $statsPerWeekday = [];
        foreach (Weekday::cases() as $weekday) {
            $total = $totals[$weekday->value];
            $statsPerWeekday[$weekday->value] = [
                'weekday' => $weekday,
                'numberOfActivities' => $total['numberOfActivities'],
                'distance' => Kilometer::from($total['distance']),
                'averageDistance' => Kilometer::from($total['numberOfActivities'] > 0 ? $total['distance'] / $total['numberOfActivities'] : 0),
                'elevation' => Meter::from($total['elevation']),
                'movingTime' => Seconds::from($total['movingTime']),
                'percentage' => $total['numberOfActivities'] > 0 && $totalMovingTime > 0 ? round($total['movingTime'] / $totalMovingTime * 100, 2) : 0,
            ];
        }

        return $statsPerWeekday;
    }
}
