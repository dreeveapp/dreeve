<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Time\Format\ProvideTimeFormats;

final readonly class DaytimeStats
{
    use ProvideTimeFormats;

    /**
     * @param array<string, int> $startHours
     */
    private function __construct(
        private Activities $activities,
        private array $startHours,
    ) {
    }

    /**
     * @param array<string, int> $startHours
     */
    public static function create(
        Activities $activities,
        array $startHours,
    ): self {
        return new self($activities, $startHours);
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $statistics = [];
        $totalMovingTime = $this->activities->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());

        $daytimes = Daytime::cases();
        foreach ($daytimes as $index => $daytime) {
            $statistics[$daytime->value] = [
                'daytime' => $daytime,
                'startHour' => $this->startHours[$daytime->value],
                'endHour' => $this->startHours[$daytimes[($index + 1) % count($daytimes)]->value],
                'numberOfWorkouts' => 0,
                'totalDistance' => 0,
                'totalElevation' => 0,
                'movingTime' => 0,
                'percentage' => 0,
                'averageDistance' => 0,
                'movingTimeInHours' => Hour::zero(),
            ];
        }

        /** @var Activity $activity */
        foreach ($this->activities as $activity) {
            $daytime = $this->findDaytime($activity->getStartDate()->getHourWithoutLeadingZero());

            ++$statistics[$daytime->value]['numberOfWorkouts'];
            $statistics[$daytime->value]['totalDistance'] += $activity->getDistance()->toFloat();
            $statistics[$daytime->value]['totalElevation'] += $activity->getElevation()->toFloat();
            $statistics[$daytime->value]['movingTime'] += $activity->getMovingTimeInSeconds();
            $statistics[$daytime->value]['averageDistance'] = $statistics[$daytime->value]['totalDistance'] / $statistics[$daytime->value]['numberOfWorkouts'];
            $statistics[$daytime->value]['movingTimeForHumans'] = $this->formatDurationAsHumanString($statistics[$daytime->value]['movingTime']);
            $statistics[$daytime->value]['movingTimeInHours'] = Seconds::from($statistics[$daytime->value]['movingTime'])->toHour();
            $statistics[$daytime->value]['percentage'] = $totalMovingTime > 0 ? round($statistics[$daytime->value]['movingTime'] / $totalMovingTime * 100, 2) : 0;
        }

        foreach ($statistics as $daytime => $statistic) {
            $statistics[$daytime]['totalDistance'] = Kilometer::from($statistic['totalDistance']);
            $statistics[$daytime]['averageDistance'] = Kilometer::from($statistic['averageDistance']);
            $statistics[$daytime]['totalElevation'] = Meter::from($statistic['totalElevation']);
        }

        return $statistics;
    }

    /**
     * @return array<int, array{daytime: Daytime, numberOfWorkouts: int}>
     */
    public function getNumberOfWorkoutsPerHour(): array
    {
        $countsPerHour = array_fill(0, 24, 0);
        foreach ($this->activities as $activity) {
            ++$countsPerHour[$activity->getStartDate()->getHourWithoutLeadingZero()];
        }

        $numberOfWorkoutsPerHour = [];
        foreach (range(0, 23) as $offset) {
            $hour = ($this->startHours[Daytime::MORNING->value] + $offset) % 24;
            $numberOfWorkoutsPerHour[$hour] = [
                'daytime' => $this->findDaytime($hour),
                'numberOfWorkouts' => $countsPerHour[$hour],
            ];
        }

        return $numberOfWorkoutsPerHour;
    }

    private function findDaytime(int $hour): Daytime
    {
        $found = Daytime::MORNING;
        foreach (Daytime::cases() as $daytime) {
            if ($this->hoursSinceMorning($this->startHours[$daytime->value]) <= $this->hoursSinceMorning($hour)) {
                $found = $daytime;
            }
        }

        return $found;
    }

    private function hoursSinceMorning(int $hour): int
    {
        return ($hour - $this->startHours[Daytime::MORNING->value] + 24) % 24;
    }
}
