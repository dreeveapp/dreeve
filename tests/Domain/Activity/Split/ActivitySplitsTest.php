<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\Split\ActivitySplit;
use App\Domain\Activity\Split\ActivitySplits;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivitySplitsTest extends TestCase
{
    /**
     * @param list<ActivitySplit> $splits
     */
    #[DataProvider('provideSplitsWithGap')]
    public function testGetOverallGapPaceInSecondsPerKm(array $splits, float $expectedGapPace): void
    {
        $overall = ActivitySplits::fromArray($splits)->getOverallGapPaceInSecondsPerKm();

        $this->assertNotNull($overall);
        $this->assertEqualsWithDelta($expectedGapPace, $overall->toFloat(), 0.01);
    }

    /**
     * @return iterable<string, array{list<ActivitySplit>, float}>
     */
    public static function provideSplitsWithGap(): iterable
    {
        yield 'equal distances calculate distance weighted average' => [
            [
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(1)->withDistanceInMeter(1000)->withGapPace(SecPerKm::from(300))->build(),
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(2)->withDistanceInMeter(1000)->withGapPace(SecPerKm::from(400))->build(),
            ],
            350.0,
        ];
        yield 'unequal distances calculate distance weighted average' => [
            [
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(1)->withDistanceInMeter(750)->withGapPace(SecPerKm::from(300))->build(),
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(2)->withDistanceInMeter(250)->withGapPace(SecPerKm::from(400))->build(),
            ],
            325.0,
        ];
        yield 'splits without gap are skipped' => [
            [
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(1)->withDistanceInMeter(1000)->withGapPace(SecPerKm::from(300))->build(),
                ActivitySplitBuilder::fromDefaults()->withSplitNumber(2)->withDistanceInMeter(1000)->build(),
            ],
            300.0,
        ];
    }

    /**
     * @param list<ActivitySplit> $splits
     */
    #[DataProvider('provideSplitsWithoutGap')]
    public function testGetOverallGapPaceInSecondsPerKmIsNullWithoutGap(array $splits): void
    {
        $this->assertNull(ActivitySplits::fromArray($splits)->getOverallGapPaceInSecondsPerKm());
    }

    /**
     * @return iterable<string, array{list<ActivitySplit>}>
     */
    public static function provideSplitsWithoutGap(): iterable
    {
        yield 'no splits have gap' => [[ActivitySplitBuilder::fromDefaults()->withSplitNumber(1)->withDistanceInMeter(1000)->build()]];
        yield 'empty collection' => [[]];
    }
}
