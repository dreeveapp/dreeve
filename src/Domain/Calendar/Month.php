<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Month
{
    public const string MONTH_ID_FORMAT = 'Y-m';
    private SerializableDateTime $firstDay;
    private SerializableDateTime $lastDay;

    private function __construct(
        private int $year,
        private int $month,
    ) {
        $this->firstDay = SerializableDateTime::createFromFormat(
            format: 'd-n-Y H:i:s',
            datetime: '01-'.$this->month.'-'.$this->year.' 00:00:00',
        );
        $this->lastDay = SerializableDateTime::fromString($this->year.'-'.$this->month.'-'.$this->firstDay->format('t'));
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public static function fromDate(SerializableDateTime $date): self
    {
        return new self(
            year: $date->getYear(),
            month: $date->getMonthWithoutLeadingZero(),
        );
    }

    public function getLabel(): string
    {
        return $this->firstDay->translatedFormat('F Y');
    }

    public function getLabelWithoutYear(): string
    {
        return $this->firstDay->translatedFormat('F');
    }

    public function getShortLabel(): string
    {
        return $this->firstDay->translatedFormat('M Y');
    }

    public function getShortLabelWithoutYear(): string
    {
        return $this->firstDay->translatedFormat('M');
    }

    public function getId(): string
    {
        return $this->firstDay->format(self::MONTH_ID_FORMAT);
    }

    public function getNumberOfDays(): int
    {
        return (int) $this->firstDay->translatedFormat('t');
    }

    public function getWeekDayOfFirstDay(): int
    {
        // Numeric representation of week day, 1 (for Monday) through 7 (for Sunday)
        return (int) $this->firstDay->translatedFormat('N');
    }

    public function getFirstDay(): SerializableDateTime
    {
        return $this->firstDay;
    }

    public function getPreviousMonth(): self
    {
        $date = $this->firstDay->modify('first day of previous month');

        return self::fromDate($date);
    }

    public function getNextMonth(): self
    {
        $date = $this->firstDay->modify('first day of next month');

        return self::fromDate($date);
    }

    public function isBefore(Month $other): bool
    {
        if ($this->getYear() < $other->getYear()) {
            return true;
        }
        if ($this->getYear() > $other->getYear()) {
            return false;
        }

        return $this->getMonth() < $other->getMonth();
    }

    public function isAfter(Month $other): bool
    {
        if ($this->getYear() > $other->getYear()) {
            return true;
        }
        if ($this->getYear() < $other->getYear()) {
            return false;
        }

        return $this->getMonth() > $other->getMonth();
    }

    public function getFrom(): SerializableDateTime
    {
        return $this->firstDay;
    }

    public function getTo(): SerializableDateTime
    {
        return $this->lastDay;
    }
}
