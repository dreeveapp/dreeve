<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;

final readonly class Calendar
{
    /** @var array<string, Activity[]> */
    private array $activitiesPerDay;

    private function __construct(
        private Month $month,
        Activities $activities,
    ) {
        $activitiesPerDay = [];
        foreach ($activities as $activity) {
            $activitiesPerDay[$activity->getStartDate()->format('Y-m-d')][] = $activity;
        }
        $this->activitiesPerDay = $activitiesPerDay;
    }

    public static function create(
        Month $month,
        Activities $activities,
    ): self {
        return new self(
            month: $month,
            activities: $activities,
        );
    }

    public function getMonth(): Month
    {
        return $this->month;
    }

    public function getDays(): Days
    {
        $previousMonth = $this->month->getPreviousMonth();
        $nextMonth = $this->month->getNextMonth();
        $numberOfDaysInPreviousMonth = $previousMonth->getNumberOfDays();
        $numberOfDays = $this->month->getNumberOfDays();
        $weekDayOfFirstDay = $this->month->getWeekDayOfFirstDay();

        $days = Days::empty();
        for ($i = 1; $i < $weekDayOfFirstDay; ++$i) {
            // Prepend with days of previous month.
            $dayNumber = $numberOfDaysInPreviousMonth - ($weekDayOfFirstDay - $i - 1);
            $days->add(Day::create(
                dayNumber: $dayNumber,
                isCurrentMonth: false,
                activities: $this->findActivitiesOn($previousMonth, $dayNumber),
            ));
        }

        for ($i = 0; $i < $numberOfDays; ++$i) {
            $dayNumber = $i + 1;

            $days->add(Day::create(
                dayNumber: $dayNumber,
                isCurrentMonth: true,
                activities: $this->findActivitiesOn($this->month, $dayNumber),
            ));
        }

        for ($i = 0; $i < count($days) % 7; ++$i) {
            // Append with days of next month.
            $dayNumber = $i + 1;

            $days->add(Day::create(
                dayNumber: $dayNumber,
                isCurrentMonth: false,
                activities: $this->findActivitiesOn($nextMonth, $dayNumber),
            ));
        }

        return $days;
    }

    private function findActivitiesOn(Month $month, int $dayNumber): Activities
    {
        $day = sprintf('%d-%02d-%02d', $month->getYear(), $month->getMonth(), $dayNumber);

        return Activities::fromArray($this->activitiesPerDay[$day] ?? []);
    }
}
