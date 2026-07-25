<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\Split\ActivitySplits;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivitySplitsTest extends TestCase
{
    /**
     * @param list<array{int, ?int}> $splitSpecs List of [distanceInMeter, gapPaceInSecondsPerKm] pairs
     */
    #[DataProvider('provideSplits')]
    public function testGetOverallGapPaceInSecondsPerKm(array $splitSpecs, ?float $expectedGapPace): void
    {
        $splits = [];
        foreach ($splitSpecs as $index => [$distanceInMeter, $gapPace]) {
            $builder = ActivitySplitBuilder::fromDefaults()
                ->withSplitNumber($index + 1)
                ->withDistanceInMeter($distanceInMeter);
            if (null !== $gapPace) {
                $builder = $builder->withGapPace(SecPerKm::from($gapPace));
            }
            $splits[] = $builder->build();
        }

        $overall = ActivitySplits::fromArray($splits)->getOverallGapPaceInSecondsPerKm();

        if (null === $expectedGapPace) {
            $this->assertNull($overall);

            return;
        }

        $this->assertNotNull($overall);
        $this->assertEqualsWithDelta($expectedGapPace, $overall->toFloat(), 0.01);
    }

    /**
     * @return iterable<string, array{list<array{int, ?int}>, ?float}>
     */
    public static function provideSplits(): iterable
    {
        yield 'equal distances calculate distance weighted average' => [[[1000, 300], [1000, 400]], 350.0];
        yield 'unequal distances calculate distance weighted average' => [[[750, 300], [250, 400]], 325.0];
        yield 'splits without gap are skipped' => [[[1000, 300], [1000, null]], 300.0];
        yield 'no splits have gap' => [[[1000, null]], null];
        yield 'empty collection' => [[], null];
    }
}
