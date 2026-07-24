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

    private function __construct(
        private Activities $activities,
    ) {
    }

    public static function create(
        Activities $activities,
    ): self {
        return new self($activities);
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $statistics = [];
        $totalMovingTime = $this->activities->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());

        foreach (Daytime::cases() as $daytime) {
            $statistics[$daytime->value] = [
                'daytime' => $daytime,
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
            $daytime = Daytime::fromSerializableDateTime($activity->getStartDate());

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
}
