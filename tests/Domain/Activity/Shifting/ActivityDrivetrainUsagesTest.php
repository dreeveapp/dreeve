<?php

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\Shifting\ActivityDrivetrainUsages;
use App\Domain\Activity\Shifting\DrivetrainPosition;
use PHPUnit\Framework\TestCase;

class ActivityDrivetrainUsagesTest extends TestCase
{
    public function testItSummarizesTheUsagePerPosition(): void
    {
        $bigRing = ActivityDrivetrainUsageBuilder::fromDefaults()
            ->withGear(DrivetrainPosition::FRONT, 2, 53)
            ->withTimeInSeconds(300)
            ->withShiftCount(2)
            ->build();
        $smallCog = ActivityDrivetrainUsageBuilder::fromDefaults()
            ->withGear(DrivetrainPosition::REAR, 7, 15)
            ->withTimeInSeconds(100)
            ->withShiftCount(10)
            ->build();
        $bigCog = ActivityDrivetrainUsageBuilder::fromDefaults()
            ->withGear(DrivetrainPosition::REAR, 1, 26)
            ->withTimeInSeconds(300)
            ->withShiftCount(5)
            ->build();
        $drivetrainUsages = ActivityDrivetrainUsages::fromArray([$bigRing, $smallCog, $bigCog]);

        $this->assertEquals(ActivityDrivetrainUsages::fromArray([$bigRing]), $drivetrainUsages->getFrontRings());
        $this->assertEquals(ActivityDrivetrainUsages::fromArray([$smallCog, $bigCog]), $drivetrainUsages->getRearCogs());
        $this->assertSame(2, $drivetrainUsages->getFrontRings()->getTotalShiftCount());
        $this->assertSame(15, $drivetrainUsages->getRearCogs()->getTotalShiftCount());
        $this->assertSame(400, $drivetrainUsages->getRearCogs()->getTotalTimeInSeconds());
        $this->assertSame(25.0, $drivetrainUsages->getRearCogs()->getTimePercentageFor($smallCog));
        $this->assertSame(75.0, $drivetrainUsages->getRearCogs()->getTimePercentageFor($bigCog));
    }

    public function testTheTimePercentageIsZeroWithoutAnyTime(): void
    {
        $drivetrainUsage = ActivityDrivetrainUsageBuilder::fromDefaults()
            ->withTimeInSeconds(0)
            ->build();

        $this->assertSame(0.0, ActivityDrivetrainUsages::fromArray([$drivetrainUsage])->getTimePercentageFor($drivetrainUsage));
    }
}
