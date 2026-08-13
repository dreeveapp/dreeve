<?php

namespace App\Tests\Domain\Gear\Maintenance\Task;

use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaintenanceTaskProgressTest extends TestCase
{
    #[DataProvider('provideInvalidIntervals')]
    public function testItShouldThrowWhenTheIntervalIsNotPositive(float $interval): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Interval must be greater than 0'));

        MaintenanceTaskProgress::from(0, $interval, '0 km', '0 km');
    }

    public static function provideInvalidIntervals(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
    }

    public function testItCapsThePercentageButNotTheCompletionRatio(): void
    {
        $progress = MaintenanceTaskProgress::from(1500, 500, '1500 km', '1000 km');

        $this->assertSame(100, $progress->getPercentage());
        $this->assertSame(3.0, $progress->getCompletionRatio());
    }

    public function testItIsOverdueWhenTheIntervalIsExceeded(): void
    {
        $progress = MaintenanceTaskProgress::from(620, 500, '620 km', '120 km');

        $this->assertTrue($progress->isOverdue());
        $this->assertTrue($progress->isDue());
        $this->assertSame(-120.0, $progress->getRemaining());
        $this->assertSame('120 km', $progress->getRemainingDescription());
    }

    public function testItIsNotOverdueOnTheExactInterval(): void
    {
        $progress = MaintenanceTaskProgress::from(500, 500, '500 km', '0 km');

        $this->assertFalse($progress->isOverdue());
        $this->assertTrue($progress->isDue());
        $this->assertSame(100, $progress->getPercentage());
        $this->assertSame(0.0, $progress->getRemaining());
    }

    public function testItReportsTheElapsedAmountAsItsDescription(): void
    {
        $progress = MaintenanceTaskProgress::from(312, 500, '312 km', '188 km');

        $this->assertSame('312 km', $progress->getDescription());
        $this->assertSame(188.0, $progress->getRemaining());
        $this->assertSame(62, $progress->getPercentage());
    }

    #[DataProvider('provideStates')]
    public function testStateThresholds(float $elapsed, bool $isZero, bool $isLow, bool $isMedium, bool $isHigh): void
    {
        $progress = MaintenanceTaskProgress::from($elapsed, 100, 'elapsed', 'remaining');

        $this->assertSame($isZero, $progress->isZero());
        $this->assertSame($isLow, $progress->isLow());
        $this->assertSame($isMedium, $progress->isMedium());
        $this->assertSame($isHigh, $progress->isHigh());
    }

    public static function provideStates(): iterable
    {
        yield 'nothing used yet' => [0, true, true, false, false];
        yield 'just under 70%' => [69, false, true, false, false];
        yield 'exactly 70%' => [70, false, false, true, false];
        yield 'exactly 90%' => [90, false, false, false, true];
        yield 'overdue' => [250, false, false, false, true];
    }
}
