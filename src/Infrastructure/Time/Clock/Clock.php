<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Clock;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Psr\Clock\ClockInterface;
use Time\Duration;

interface Clock extends ClockInterface
{
    public function now(): SerializableDateTime;

    public function sleep(Duration $duration): void;
}
