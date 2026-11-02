<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Clock;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Clock\ClockInterface;

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
}
