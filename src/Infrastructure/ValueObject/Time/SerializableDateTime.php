<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Time;

use Carbon\Carbon;

class SerializableDateTime extends \DateTimeImmutable implements \JsonSerializable, \Stringable
{
    public static function fromDateTimeImmutable(\DateTimeImmutable $date): self
    {
        return self::fromString($date->format('Y-m-d H:i:s'));
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function fromString(string $string, ?\DateTimeZone $timezone = null): self
    {
        return new self($string, $timezone);
    }

    public static function fromTimestamp(int $unixTimestamp, ?\DateTimeZone $timezone = null): self
    {
        return self::fromString('now', $timezone)->setTimestamp($unixTimestamp);
    }

    public static function createFromFormat(string $format, string $datetime, ?\DateTimeZone $timezone = null): self
    {
        if (!$datetime = parent::createFromFormat($format, $datetime, $timezone)) {
            throw new \InvalidArgumentException(sprintf('Invalid date format %s for %s', $format, $datetime)); // @codeCoverageIgnore
        }

        return self::fromString(
            $datetime->format('Y-m-d H:i:s'),
        );
    }

    public static function some(): self
    {
        return self::fromString('2025-01-01 00:00:00');
    }

    public function __toString(): string
    {
        return $this->iso();
    }

    public function jsonSerialize(): string
    {
        return $this->iso();
    }

    public function iso(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function translatedFormat(string $format): string
    {
        return new Carbon($this)->translatedFormat($format);
    }

    public function toUtc(): self
    {
        return $this->toTimezone(SerializableTimezone::UTC());
    }

    public function toTimezone(SerializableTimezone $timezone): self
    {
        return $this->setTimezone($timezone);
    }

    public function getHourWithoutLeadingZero(): int
    {
        return (int) $this->format('G');
    }

    public function getMinutesWithoutLeadingZero(): int
    {
        return intval($this->format('i'));
    }

    public function getMonthWithoutLeadingZero(): int
    {
        return intval($this->format('n'));
    }

    public function getYear(): int
    {
        return (int) $this->format('Y');
    }

    public function getDayOfTheWeek(): int
    {
        return (int) $this->format('N');
    }

    public function isAfterOrOn(SerializableDateTime $that): bool
    {
        return $this >= $that;
    }

    public function isBeforeOrOn(SerializableDateTime $that): bool
    {
        return $this <= $that;
    }

    public function isBefore(SerializableDateTime $that): bool
    {
        return $this < $that;
    }

    public function isAfter(SerializableDateTime $that): bool
    {
        return $this > $that;
    }
}
