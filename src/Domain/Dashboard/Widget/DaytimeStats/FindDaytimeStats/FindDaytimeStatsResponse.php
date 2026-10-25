<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\Widget\DaytimeStats\Daytime;
use App\Domain\Dashboard\Widget\DaytimeStats\DaytimeStartHours;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindDaytimeStatsResponse implements Response
{
    /**
     * @param array<int, array{activityType: ActivityType, hour: int, numberOfActivities: int, distance: Kilometer, elevation: Meter, movingTime: Seconds}> $statsPerHour
     */
    public function __construct(
        private array $statsPerHour,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function getNumberOfActivitiesPerHour(DaytimeStartHours $startHours, ?ActivityType $activityType): array
    {
        $numberOfActivitiesPerHour = array_fill_keys($startHours->getHoursInDisplayOrder(), 0);
        foreach ($this->statsPerHour as $stats) {
            if ($activityType instanceof ActivityType && $stats['activityType'] !== $activityType) {
                continue;
            }
            $numberOfActivitiesPerHour[$stats['hour']] += $stats['numberOfActivities'];
        }

        return $numberOfActivitiesPerHour;
    }

    /**
     * @return array<string, array{daytime: Daytime, startHour: int, endHour: int, numberOfActivities: int, distance: Kilometer, averageDistance: Kilometer, elevation: Meter, movingTime: Seconds}>
     */
    public function getStatsPerDaytime(DaytimeStartHours $startHours, ?ActivityType $activityType): array
    {
        $totals = [];
        foreach (Daytime::cases() as $daytime) {
            $totals[$daytime->value] = [
                'numberOfActivities' => 0,
                'distance' => 0.0,
                'elevation' => 0.0,
                'movingTime' => 0,
            ];
        }

        foreach ($this->statsPerHour as $stats) {
            if ($activityType instanceof ActivityType && $stats['activityType'] !== $activityType) {
                continue;
            }
            $daytime = $startHours->findDaytime($stats['hour'])->value;
            $totals[$daytime]['numberOfActivities'] += $stats['numberOfActivities'];
            $totals[$daytime]['distance'] += $stats['distance']->toFloat();
            $totals[$daytime]['elevation'] += $stats['elevation']->toFloat();
            $totals[$daytime]['movingTime'] += $stats['movingTime']->toInt();
        }

        $statsPerDaytime = [];
        foreach (Daytime::cases() as $daytime) {
            $total = $totals[$daytime->value];
            $statsPerDaytime[$daytime->value] = [
                'daytime' => $daytime,
                'startHour' => $startHours->getStartHour($daytime),
                'endHour' => $startHours->getEndHour($daytime),
                'numberOfActivities' => $total['numberOfActivities'],
                'distance' => Kilometer::from($total['distance']),
                'averageDistance' => Kilometer::from($total['numberOfActivities'] > 0 ? $total['distance'] / $total['numberOfActivities'] : 0),
                'elevation' => Meter::from($total['elevation']),
                'movingTime' => Seconds::from($total['movingTime']),
            ];
        }

        return $statsPerDaytime;
    }
}
