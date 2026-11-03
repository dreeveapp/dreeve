<?php

namespace App\Tests\Infrastructure\Time\Clock;

use App\Infrastructure\Time\Clock\SystemClock;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

class SystemClockTest extends TestCase
{
    #[TestWith(['Europe/Brussels', '2023-10-17 18:15:04.123456 Europe/Brussels'])]
    #[TestWith(['America/New_York', '2023-10-17 12:15:04.123456 America/New_York'])]
    #[TestWith([null, '2023-10-17 16:15:04.123456 UTC'])]
    public function testNow(?string $timezone, string $expectedDateTime): void
    {
        $clock = new SystemClock(new Clock(
            new MockClock('2023-10-17 16:15:04.123456', 'UTC'),
            SerializableTimezone::fromOptionalString($timezone),
        ));

        $now = $clock->now();

        $this->assertEquals($expectedDateTime, $now->format('Y-m-d H:i:s.u e'));
    }
}
