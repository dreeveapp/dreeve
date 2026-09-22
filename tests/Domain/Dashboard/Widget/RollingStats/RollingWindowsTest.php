<?php

namespace App\Tests\Domain\Dashboard\Widget\RollingStats;

use App\Domain\Dashboard\Widget\RollingStats\RollingWindow;
use App\Domain\Dashboard\Widget\RollingStats\RollingWindows;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class RollingWindowsTest extends TestCase
{
    public function testItBuildsOneWindowPerDay(): void
    {
        $rollingWindows = RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 3,
        );

        $this->assertEquals(
            ['2025-01-01', '2025-01-02', '2025-01-03', '2025-01-04', '2025-01-05'],
            $rollingWindows->map(fn (RollingWindow $rollingWindow): string => $rollingWindow->getTo()->format('Y-m-d'))
        );
    }

    public function testTheWindowIsInclusiveOnBothEnds(): void
    {
        $rollingWindows = RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 3,
        );

        $this->assertEquals('2024-12-30', $rollingWindows->getFirst()->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-01', $rollingWindows->getFirst()->getTo()->format('Y-m-d'));
        $this->assertEquals('2025-01-03', $rollingWindows->getLast()->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-05', $rollingWindows->getLast()->getTo()->format('Y-m-d'));
    }

    public function testASevenDayWindowEndingOnASundayCoversThatCalendarWeek(): void
    {
        $rollingWindows = RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-12'),
            now: SerializableDateTime::fromString('2025-01-12'),
            windowInDays: 7,
        );

        $this->assertEquals('2025-01-06', $rollingWindows->getFirst()->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-12', $rollingWindows->getFirst()->getTo()->format('Y-m-d'));
    }
}
