<?php

namespace App\Domain\Activity;

use App\Domain\Activity\FindActivityTotals\FindActivityTotalsResponse;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Carbon\CarbonInterval;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityTotals
{
    use ProvideTimeFormats;

    private function __construct(
        private FindActivityTotalsResponse $totals,
        private SerializableDateTime $now,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        FindActivityTotalsResponse $totals,
        SerializableDateTime $now,
        TranslatorInterface $translator): self
    {
        return new self(
            totals: $totals,
            now: $now,
            translator: $translator,
        );
    }

    public function getDistance(): Kilometer
    {
        return $this->totals->getTotalDistance();
    }

    public function getElevation(): Meter
    {
        return $this->totals->getTotalElevation();
    }

    public function getCalories(): int
    {
        return $this->totals->getTotalCalories();
    }

    public function getTotalActivities(): int
    {
        return $this->totals->getTotalActivities();
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsHumanString($this->totals->getTotalMovingTimeInSeconds());
    }

    public function getMovingTimeInHours(): int
    {
        return (int) round(CarbonInterval::seconds($this->totals->getTotalMovingTimeInSeconds())->cascade()->totalHours);
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->totals->getFirstActivityStartDate() ?? throw new \RuntimeException('No activities found');
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
        return $this->totals->getTotalDaysOfWorkingOut();
    }
}
