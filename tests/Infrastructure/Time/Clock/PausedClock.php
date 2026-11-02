<?php

namespace App\Tests\Infrastructure\Time\Clock;

use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Clock\MockClock;

class PausedClock implements Clock
{
    private function __construct(private readonly MockClock $clock)
    {
    }

    public static function on(SerializableDateTime $on): PausedClock
    {
        return new self(new MockClock($on));
    }

    public static function fromString(string $dateTime): PausedClock
    {
        return self::on(SerializableDateTime::fromString($dateTime));
    }

    public function now(): SerializableDateTime
    {
        return SerializableDateTime::createFromInterface($this->clock->now());
    }
}
