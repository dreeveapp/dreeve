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

        $firstWindow = $rollingWindows->getFirst();
        $this->assertNotNull($firstWindow);
        $lastWindow = $rollingWindows->getLast();
        $this->assertNotNull($lastWindow);
        $this->assertEquals('2024-12-30', $firstWindow->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-01', $firstWindow->getTo()->format('Y-m-d'));
        $this->assertEquals('2025-01-03', $lastWindow->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-05', $lastWindow->getTo()->format('Y-m-d'));
    }

    public function testASevenDayWindowEndingOnASundayCoversThatCalendarWeek(): void
    {
        $rollingWindows = RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-12'),
            now: SerializableDateTime::fromString('2025-01-12'),
            windowInDays: 7,
        );

        $firstWindow = $rollingWindows->getFirst();
        $this->assertNotNull($firstWindow);
        $this->assertEquals('2025-01-06', $firstWindow->getFrom()->format('Y-m-d'));
        $this->assertEquals('2025-01-12', $firstWindow->getTo()->format('Y-m-d'));
    }
}
