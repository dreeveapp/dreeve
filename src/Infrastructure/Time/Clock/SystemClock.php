<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Clock;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Clock\ClockInterface;
use Time\Duration;

readonly class SystemClock implements Clock
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function now(): SerializableDateTime
    {
        return SerializableDateTime::createFromInterface($this->clock->now());
    }

    public function sleep(Duration $duration): void
    {
        $this->clock->sleep($duration->seconds);
    }
}
