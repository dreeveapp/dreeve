<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\Split\ActivitySplits;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use PHPUnit\Framework\TestCase;

class ActivitySplitsTest extends TestCase
{
    public function testGetOverallGapPaceCalculatesDistanceWeightedAverage(): void
    {
        $splits = ActivitySplits::fromArray([
            ActivitySplitBuilder::fromDefaults()
                ->withDistanceInMeter(1000)
                ->withGapPace(SecPerKm::from(300))
                ->build(),
            ActivitySplitBuilder::fromDefaults()
                ->withSplitNumber(2)
                ->withDistanceInMeter(1000)
                ->withGapPace(SecPerKm::from(400))
                ->build(),
        ]);

        $overall = $splits->getOverallGapPaceInSecondsPerKm();

        $this->assertNotNull($overall);
        $this->assertEqualsWithDelta(350.0, $overall->toFloat(), 0.01);
    }

    public function testGetOverallGapPaceWithUnequalDistances(): void
    {
        $splits = ActivitySplits::fromArray([
            ActivitySplitBuilder::fromDefaults()
                ->withDistanceInMeter(750)
                ->withGapPace(SecPerKm::from(300))
                ->build(),
            ActivitySplitBuilder::fromDefaults()
                ->withSplitNumber(2)
                ->withDistanceInMeter(250)
                ->withGapPace(SecPerKm::from(400))
                ->build(),
        ]);

        $overall = $splits->getOverallGapPaceInSecondsPerKm();

        $this->assertNotNull($overall);
        $this->assertEqualsWithDelta(325.0, $overall->toFloat(), 0.01);
    }

    public function testGetOverallGapPaceSkipsSplitsWithoutGap(): void
    {
        $splits = ActivitySplits::fromArray([
            ActivitySplitBuilder::fromDefaults()
                ->withDistanceInMeter(1000)
                ->withGapPace(SecPerKm::from(300))
                ->build(),
            ActivitySplitBuilder::fromDefaults()
                ->withSplitNumber(2)
                ->withDistanceInMeter(1000)
                ->build(),
        ]);

        $overall = $splits->getOverallGapPaceInSecondsPerKm();

        $this->assertNotNull($overall);
        $this->assertEqualsWithDelta(300.0, $overall->toFloat(), 0.01);
    }

    public function testGetOverallGapPaceReturnsNullWhenNoSplitsHaveGap(): void
    {
        $splits = ActivitySplits::fromArray([
            ActivitySplitBuilder::fromDefaults()
                ->withDistanceInMeter(1000)
                ->build(),
        ]);

        $this->assertNull($splits->getOverallGapPaceInSecondsPerKm());
    }

    public function testGetOverallGapPaceReturnsNullForEmptyCollection(): void
    {
        $this->assertNull(ActivitySplits::empty()->getOverallGapPaceInSecondsPerKm());
    }
}
