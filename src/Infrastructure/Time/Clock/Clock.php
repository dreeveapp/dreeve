<?php

declare(strict_types=1);

namespace App\Infrastructure\Time\Clock;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Psr\Clock\ClockInterface;

interface Clock extends ClockInterface
{
    public function now(): SerializableDateTime;

    public function sleep(int $seconds): void;
}
