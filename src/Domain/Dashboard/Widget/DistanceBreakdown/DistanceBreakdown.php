<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DistanceBreakdown;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Time\Format\ProvideTimeFormats;

final readonly class DistanceBreakdown
{
    use ProvideTimeFormats;

    private function __construct(
        private Activities $activities,
        private UnitSystem $unitSystem,
    ) {
    }

    public static function create(
        Activities $activities,
        UnitSystem $unitSystem,
    ): self {
        return new self(
            activities: $activities,
            unitSystem: $unitSystem
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function build(): array
    {
        if ($this->activities->isEmpty()) {
            return [];
        }

        $numberOfBreakdowns = 11;
        $statistics = [];
        $longestDistanceForActivity = Kilometer::from($this->activities->max(
            fn (Activity $activity): float => $activity->getDistance()->toFloat()
        ))->toUnitSystem($this->unitSystem);

        if ($longestDistanceForActivity->isZeroOrLower()) {
            return [];
        }

        $scaleForBreakdowns = match (true) {
            $longestDistanceForActivity->toFloat() <= 5 => 1,
            $longestDistanceForActivity->toFloat() <= 10 => 2,
            $longestDistanceForActivity->toFloat() <= 15 => 3,
            $longestDistanceForActivity->toFloat() <= 20 => 4,
            default => 5,
        };

        $breakdownOnDistance = (int) ceil(($longestDistanceForActivity->toFloat() / $numberOfBreakdowns) / $scaleForBreakdowns) * $scaleForBreakdowns;

        /** @var int[] $range */
        $range = range($breakdownOnDistance, ceil($longestDistanceForActivity->toFloat() / $breakdownOnDistance) * $breakdownOnDistance, $breakdownOnDistance);
        foreach ($range as $breakdownLimit) {
            $min = $breakdownLimit - $breakdownOnDistance;
            $statistics[$breakdownLimit] = [
                'label' => sprintf('%d - %d %s', $min, $breakdownLimit, $longestDistanceForActivity->getSymbol()),
                'min' => $min * 10,
                'max' => $breakdownLimit * 10,
                'numberOfWorkouts' => 0,
                'totalDistance' => 0,
                'totalElevation' => 0,
                'movingTime' => 0,
                'averageDistance' => 0,
                'averageSpeed' => 0,
            ];
        }

        foreach ($this->activities as $activity) {
            /** @var Activity $activity */
            $distance = $activity->getDistance()->toUnitSystem($this->unitSystem);
            if ($distance->isZeroOrLower()) {
                continue;
            }
            $elevation = $activity->getElevation()->toUnitSystem($this->unitSystem);
            $distanceBreakdown = (int) ceil($distance->toFloat() / $breakdownOnDistance) * $breakdownOnDistance;

            ++$statistics[$distanceBreakdown]['numberOfWorkouts'];
            $statistics[$distanceBreakdown]['totalDistance'] = ($statistics[$distanceBreakdown]['totalDistance'] ?? 0) + $distance->toFloat();
            $statistics[$distanceBreakdown]['totalElevation'] = ($statistics[$distanceBreakdown]['totalElevation'] ?? 0) + $elevation->toFloat();
            $statistics[$distanceBreakdown]['movingTime'] = ($statistics[$distanceBreakdown]['movingTime'] ?? 0) + $activity->getMovingTimeInSeconds();
            $statistics[$distanceBreakdown]['movingTimeInHours'] = Seconds::from($statistics[$distanceBreakdown]['movingTime'])->toHour();
            $statistics[$distanceBreakdown]['averageDistance'] = $statistics[$distanceBreakdown]['totalDistance'] / $statistics[$distanceBreakdown]['numberOfWorkouts'];
            if ($statistics[$distanceBreakdown]['movingTime'] > 0) {
                $statistics[$distanceBreakdown]['averageSpeed'] = ($statistics[$distanceBreakdown]['totalDistance'] / $statistics[$distanceBreakdown]['movingTime']) * 3600;
            }
            $statistics[$distanceBreakdown]['movingTimeForHumans'] = $this->formatDurationAsHumanString($statistics[$distanceBreakdown]['movingTime']);
        }

        foreach ($statistics as $distanceBreakdown => $statistic) {
            $statistics[$distanceBreakdown]['totalDistance'] = $this->unitSystem->distance($statistic['totalDistance']);
            $statistics[$distanceBreakdown]['averageDistance'] = $this->unitSystem->distance($statistic['averageDistance']);
            $statistics[$distanceBreakdown]['totalElevation'] = $this->unitSystem->elevation($statistic['totalElevation']);
            $statistics[$distanceBreakdown]['averageSpeed'] = $this->unitSystem->speed($statistic['averageSpeed'] ?? 0);
        }

        return $statistics;
    }
}
