<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WeekdayStats
{
    use ProvideTimeFormats;

    private function __construct(
        private Activities $activities,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        Activities $activities,
        TranslatorInterface $translator,
    ): self {
        return new self(
            activities: $activities,
            translator: $translator,
        );
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $statistics = [];
        $daysOfTheWeekMap = [
            $this->translator->trans('Sun.'),
            $this->translator->trans('Mon.'),
            $this->translator->trans('Tue.'),
            $this->translator->trans('Wed.'),
            $this->translator->trans('Thu.'),
            $this->translator->trans('Fri.'),
            $this->translator->trans('Sat.'),
        ];
        $totalMovingTime = $this->activities->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());

        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekDay) {
            $statistics[$daysOfTheWeekMap[$weekDay]] = [
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
            $weekDay = $daysOfTheWeekMap[$activity->getStartDate()->format('w')];

            ++$statistics[$weekDay]['numberOfWorkouts'];

            $statistics[$weekDay]['totalDistance'] = ($statistics[$weekDay]['totalDistance'] ?? 0) + $activity->getDistance()->toFloat();
            $statistics[$weekDay]['totalElevation'] = ($statistics[$weekDay]['totalElevation'] ?? 0) + $activity->getElevation()->toFloat();
            $statistics[$weekDay]['movingTime'] = ($statistics[$weekDay]['movingTime'] ?? 0) + $activity->getMovingTimeInSeconds();
            $statistics[$weekDay]['averageDistance'] = $statistics[$weekDay]['totalDistance'] / $statistics[$weekDay]['numberOfWorkouts'];
            $statistics[$weekDay]['movingTimeForHumans'] = $this->formatDurationAsHumanString($statistics[$weekDay]['movingTime']);
            $statistics[$weekDay]['movingTimeInHours'] = Seconds::from($statistics[$weekDay]['movingTime'])->toHour();
            $statistics[$weekDay]['percentage'] = $totalMovingTime > 0 ? round($statistics[$weekDay]['movingTime'] / $totalMovingTime * 100, 2) : 0;
        }

        foreach ($statistics as $weekDay => $statistic) {
            $statistics[$weekDay]['totalDistance'] = Kilometer::from($statistic['totalDistance']);
            $statistics[$weekDay]['averageDistance'] = Kilometer::from($statistic['averageDistance']);
            $statistics[$weekDay]['totalElevation'] = Meter::from($statistic['totalElevation']);
        }

        return $statistics;
    }
}
