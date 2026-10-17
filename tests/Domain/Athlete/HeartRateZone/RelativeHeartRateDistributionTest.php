<?php

namespace App\Tests\Domain\Athlete\HeartRateZone;

use App\Domain\Athlete\HeartRateZone\RelativeHeartRateDistribution;
use PHPUnit\Framework\TestCase;

class RelativeHeartRateDistributionTest extends TestCase
{
    public function testGetBins(): void
    {
        $relativeHeartRateDistribution = RelativeHeartRateDistribution::fromSecondsPerPercentage([
            87 => 99.4,
            40 => 1.0,
            73 => 400.0,
            62 => 500.0,
            120 => 1.0,
        ]);

        $this->assertEquals(
            array_replace(array_fill(60, 31, 0), [60 => 1, 62 => 500, 73 => 400, 87 => 99, 90 => 1]),
            $relativeHeartRateDistribution->getBins(),
        );
        $this->assertEquals(1001, $relativeHeartRateDistribution->getTotalTimeInSeconds());
    }

    public function testGetBinsWithoutData(): void
    {
        $relativeHeartRateDistribution = RelativeHeartRateDistribution::fromSecondsPerPercentage([]);

        $this->assertEquals([], $relativeHeartRateDistribution->getBins());
        $this->assertEquals(0, $relativeHeartRateDistribution->getTotalTimeInSeconds());
    }
}
