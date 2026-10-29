<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Time;

final readonly class DateRange
{
    private function __construct(
        private SerializableDateTime $from,
        private SerializableDateTime $till)
    {
        if ($from > $till) {
            throw new \InvalidArgumentException('invalid DateRange: '.$from.' till '.$till);
        }
    }

    public static function fromDates(SerializableDateTime $from, SerializableDateTime $till): self
    {
        return new self(
            from: $from,
            till: $till
        );
    }

    public static function lastXDays(SerializableDateTime $now, int $numberOfDays): self
    {
        $interval = \DateInterval::createFromDateString($numberOfDays.' days');

        return new self(
            from: $now->sub($interval),
            till: $now
        );
    }

    public static function upUntilNow(): self
    {
        return new self(
            from: SerializableDateTime::fromString('1970-01-01 00:00:00'),
            till: SerializableDateTime::fromString('now'),
        );
    }

    public function getFrom(): SerializableDateTime
    {
        return $this->from;
    }

    public function getTill(): SerializableDateTime
    {
        return $this->till;
    }

    public function getNumberOfDays(): int
    {
        return (int) $this->from->diff($this->till)->format('%a') + 1;
    }
}
