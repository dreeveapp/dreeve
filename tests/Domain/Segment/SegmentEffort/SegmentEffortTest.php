<?php

namespace App\Tests\Domain\Segment\SegmentEffort;

use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
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

    public function testGetPaceInSecPerKm(): void
    {
        $segmentEffort = SegmentEffortBuilder::fromDefaults()
            ->withElapsedTimeInSeconds(1000)
            ->build();

        $this->assertInstanceOf(
            SecPerKm::class,
            $segmentEffort->getPaceInSecPerKm()
        );
    }

    public function testGetPaceInSecPer100Meter(): void
    {
        $segmentEffort = SegmentEffortBuilder::fromDefaults()
            ->withElapsedTimeInSeconds(1000)
            ->build();

        $this->assertInstanceOf(
            SecPer100Meter::class,
            $segmentEffort->getPaceInSecPer100Meter()
        );
    }
}
