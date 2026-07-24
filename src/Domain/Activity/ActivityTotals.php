<?php

namespace App\Domain\Activity;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Carbon\CarbonInterval;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ActivityTotals
{
    use ProvideTimeFormats;

    public static ?ActivityTotals $instance = null;

    private readonly Kilometer $totalDistance;
    private readonly Meter $totalElevation;
    private readonly int $totalCalories;
    private readonly int $totalMovingTimeInSeconds;
    private readonly int $totalActivities;

    private function __construct(
        private readonly Activities $activities,
        private readonly SerializableDateTime $now,
        private readonly TranslatorInterface $translator,
    ) {
        $this->totalDistance = Kilometer::from(
            $this->activities->sum(fn (Activity $activity): float => $activity->getDistance()->toFloat())
        );
        $this->totalElevation = Meter::from(
            $this->activities->sum(fn (Activity $activity): float => $activity->getElevation()->toFloat())
        );
        $this->totalCalories = (int) $this->activities->sum(fn (Activity $activity): ?int => $activity->getCalories());
        $this->totalMovingTimeInSeconds = (int) $this->activities->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());
        $this->totalActivities = count($this->activities);
    }

    public static function getInstance(
        Activities $activities,
        SerializableDateTime $now,
        TranslatorInterface $translator): self
    {
        if (!self::$instance instanceof ActivityTotals) {
            self::$instance = new self(
                activities: $activities,
                now: $now,
                translator: $translator,
            );
        }

        return self::$instance;
    }

    public function getDistance(): Kilometer
    {
        return $this->totalDistance;
    }

    public function getElevation(): Meter
    {
        return $this->totalElevation;
    }

    public function getCalories(): int
    {
        return $this->totalCalories;
    }

    public function getTotalActivities(): int
    {
        return $this->totalActivities;
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsHumanString($this->totalMovingTimeInSeconds);
    }

    public function getMovingTimeInHours(): int
    {
        return (int) round(CarbonInterval::seconds($this->totalMovingTimeInSeconds)->cascade()->totalHours);
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->activities->getFirstActivityStartDate();
    }

    public function getDailyAverage(): Kilometer
    {
        $diff = $this->getStartDate()->diff($this->now);
        if (0 === $diff->days) {
            return Kilometer::zero();
        }

        return Kilometer::from($this->getDistance()->toFloat() / $diff->days);
    }

    public function getWeeklyAverage(): Kilometer
    {
        $diff = $this->getStartDate()->diff($this->now);
        if (0 === $diff->days) {
            return Kilometer::zero();
        }

        return Kilometer::from($this->getDistance()->toFloat() / ceil($diff->days / 7));
    }

    public function getMonthlyAverage(): Kilometer
    {
        $diff = $this->getStartDate()->diff($this->now);
        if (0 === $diff->days) {
            return Kilometer::zero();
        }

        return Kilometer::from($this->getDistance()->toFloat() / (($diff->y * 12) + $diff->m + 1));
    }

    public function getTotalDaysSinceFirstActivity(): string
    {
        $join = $this->translator->trans('and');

        return CarbonInterval::diff($this->getStartDate(), $this->now)->cascade()->forHumans(['minimumUnit' => 'day', 'join' => [
            ' ', sprintf(' %s ', $join),
        ], 'parts' => 2]);
    }

    public function getTotalDaysOfWorkingOut(): int
    {
        return count(array_unique($this->activities->map(fn (Activity $activity) => $activity->getStartDate()->format('Ymd'))));
    }
}
