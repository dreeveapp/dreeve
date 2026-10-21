<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindStreaks;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FindStreaksResponse implements Response
{
    public function __construct(
        private int $longestDayStreak,
        private int $currentDayStreak,
        private ?SerializableDateTime $currentDayStreakStartDate,
        private int $longestWeekStreak,
        private int $currentWeekStreak,
        private ?SerializableDateTime $currentWeekStreakStartDate,
        private int $longestMonthStreak,
        private int $currentMonthStreak,
        private ?SerializableDateTime $currentMonthStreakStartDate,
        private ?DateRange $longestDayStreakPeriod,
        private ?DateRange $longestWeekStreakPeriod,
        private ?DateRange $longestMonthStreakPeriod,
        private ?DateRange $longestBreak,
    ) {
    }

    public function getCurrentDayStreak(): int
    {
        return $this->currentDayStreak;
    }

    public function getCurrentDayStreakStartDate(): ?SerializableDateTime
    {
        return $this->currentDayStreakStartDate;
    }

    public function getLongestDayStreak(): int
    {
        return $this->longestDayStreak;
    }

    public function getCurrentWeekStreak(): int
    {
        return $this->currentWeekStreak;
    }

    public function getCurrentWeekStreakStartDate(): ?SerializableDateTime
    {
        return $this->currentWeekStreakStartDate;
    }

    public function getLongestWeekStreak(): int
    {
        return $this->longestWeekStreak;
    }

    public function getLongestMonthStreak(): int
    {
        return $this->longestMonthStreak;
    }

    public function getCurrentMonthStreak(): int
    {
        return $this->currentMonthStreak;
    }

    public function getCurrentMonthStreakStartDate(): ?SerializableDateTime
    {
        return $this->currentMonthStreakStartDate;
    }

    public function getLongestDayStreakPeriod(): ?DateRange
    {
        return $this->longestDayStreakPeriod;
    }

    public function getLongestWeekStreakPeriod(): ?DateRange
    {
        return $this->longestWeekStreakPeriod;
    }

    public function getLongestMonthStreakPeriod(): ?DateRange
    {
        return $this->longestMonthStreakPeriod;
    }

    public function getLongestBreak(): ?DateRange
    {
        return $this->longestBreak;
    }
}
