<?php

namespace App\Tests\Domain\Segment\SegmentEffort;

use App\Infrastructure\Measurement\Velocity\KmPerHour;
use PHPUnit\Framework\TestCase;

class SegmentEffortTest extends TestCase
{
    public function testGetAverageSpeed(): void
    {
        $segmentEffort = SegmentEffortBuilder::fromDefaults()
            ->withElapsedTimeInSeconds(0)
            ->build();

        $this->assertEquals(
            KmPerHour::zero(),
            $segmentEffort->getAverageSpeed()
        );
    }

    public function testGetPace(): void
    {
        $segmentEffort = SegmentEffortBuilder::fromDefaults()
            ->withElapsedTimeInSeconds(1000)
            ->build();

        $this->assertEqualsWithDelta(10000, $segmentEffort->getPaceInSecPerKm()->toFloat(), 0.01);
        $this->assertEqualsWithDelta(1000, $segmentEffort->getPaceInSecPer100Meter()->toFloat(), 0.01);
    }
}
