<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Gap;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\Gap\GapCalculator;
use App\Domain\Activity\Gap\GapSegment;
use PHPUnit\Framework\TestCase;

final class GapCalculatorTest extends TestCase
{
    public function testItPreservesZeroDistanceDurationAndSkipsNonIncreasingTimestamps(): void
    {
        $calculator = GapCalculator::create(smoothingWindowSize: 1);

        $segments = iterator_to_array($calculator->calculateSegments([
            [
                'lat' => 51.0,
                'lon' => 4.0,
                'ele' => 10.0,
                'timestamp' => 1700000000,
            ],
            [
                'lat' => 51.0,
                'lon' => 4.0,
                'ele' => 20.0,
                'timestamp' => 1700000030,
            ],
            [
                'lat' => 51.0009,
                'lon' => 4.0,
                'ele' => 25.0,
                'timestamp' => 1700000030,
            ],
            [
                'lat' => 51.0018,
                'lon' => 4.0,
                'ele' => 30.0,
                'timestamp' => 1700000060,
            ],
        ]), false);

        self::assertCount(1, $segments);
        self::assertSame(60, $segments[0]->getDurationInSeconds());
    }

    public function testItPreservesRepeatedCoordinateDuration(): void
    {
        $calculator = GapCalculator::create(smoothingWindowSize: 1);

        $segments = iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 0],
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 10],
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 20],
            ['lat' => 51.0009, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 30],
        ]), false);

        self::assertCount(1, $segments);
        self::assertSame(30, $segments[0]->getDurationInSeconds());
    }

    public function testItSmoothsElevationSpikesBeforeCalculatingGrade(): void
    {
        $unsmoothedCalculator = GapCalculator::create(smoothingWindowSize: 1);
        $smoothedCalculator = GapCalculator::create(smoothingWindowSize: 5);

        $unsmoothedSegments = iterator_to_array($unsmoothedCalculator->calculateSegments($this->trackPointsWithElevationSpike()), false);
        $smoothedSegments = iterator_to_array($smoothedCalculator->calculateSegments($this->trackPointsWithElevationSpike()), false);

        self::assertCount(\count($unsmoothedSegments), $smoothedSegments);
        self::assertGreaterThan(
            max(array_map(static fn (GapSegment $segment): float => abs($segment->getGrade()), $smoothedSegments)),
            max(array_map(static fn (GapSegment $segment): float => abs($segment->getGrade()), $unsmoothedSegments)),
        );
    }

    public function testItKeepsEdgeSegmentsWhenDistanceWindowOverlapsTrackBoundaries(): void
    {
        $calculator = GapCalculator::create(smoothingWindowSize: 3);

        $segments = iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 0],
            ['lat' => 51.0001, 'lon' => 4.0000, 'ele' => 11.0, 'timestamp' => 6],
            ['lat' => 51.0002, 'lon' => 4.0000, 'ele' => 12.0, 'timestamp' => 12],
        ]), false);

        self::assertCount(2, $segments);
        self::assertGreaterThan(0.0, $segments[0]->getGrade());
        self::assertGreaterThan(0.0, $segments[1]->getGrade());
    }

    public function testSupportsGapStatsOnActivityType(): void
    {
        self::assertTrue(ActivityType::RUN->supportsGapStats());
        self::assertFalse(ActivityType::RIDE->supportsGapStats());
        self::assertFalse(ActivityType::WALK->supportsGapStats());
        self::assertFalse(ActivityType::WATER_SPORTS->supportsGapStats());
        self::assertFalse(ActivityType::FITNESS->supportsGapStats());
    }

    public function testItReturnsEmptyResultForFewerThanTwoPoints(): void
    {
        $calculator = GapCalculator::create();

        self::assertSame([], iterator_to_array($calculator->calculateSegments([]), false));
        self::assertSame([], iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0, 'lon' => 4.0, 'ele' => 10.0, 'timestamp' => 1700000000],
        ]), false));
    }

    public function testItTreatsFlatTerrainAsNeutral(): void
    {
        $calculator = GapCalculator::create(smoothingWindowSize: 1);

        $segments = iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 1700000000],
            ['lat' => 51.0009, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 1700000030],
            ['lat' => 51.0018, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 1700000060],
        ]), false);

        self::assertCount(2, $segments);
        foreach ($segments as $segment) {
            self::assertSame(0.0, $segment->getGrade());
            self::assertEqualsWithDelta(1.0, $segment->getGapMultiplier(), 0.01);
        }
    }

    public function testItRejectsInvalidConfiguration(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Smoothing window size must be at least 1.'));
        GapCalculator::create(smoothingWindowSize: 0);
    }

    public function testItRejectsInvalidGradeDistanceWindow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Grade distance window must be greater than 0.'));
        GapCalculator::create(gradeDistanceWindowInMeters: 0.0);
    }

    public function testItRejectsInvalidGradeRange(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Minimum grade must be lower than maximum grade.'));
        GapCalculator::create(minGrade: 0.5, maxGrade: 0.5);
    }

    public function testItRejectsMissingTimestamp(): void
    {
        $calculator = GapCalculator::create();

        $this->expectExceptionObject(new \InvalidArgumentException('Track point is missing required field "timestamp".'));
        iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0, 'lon' => 4.0, 'ele' => 10.0],
        ]), false);
    }

    public function testItRejectsUnsupportedTimestampType(): void
    {
        $calculator = GapCalculator::create();

        $this->expectExceptionObject(new \InvalidArgumentException('Unsupported timestamp value provided.'));
        iterator_to_array($calculator->calculateSegments([
            ['lat' => 51.0, 'lon' => 4.0, 'ele' => 10.0, 'timestamp' => 3.14],
        ]), false);
    }

    /**
     * @return list<array<string, float|int>>
     */
    private function trackPointsWithElevationSpike(): array
    {
        return [
            ['lat' => 51.0000, 'lon' => 4.0000, 'ele' => 10.0, 'timestamp' => 0],
            ['lat' => 51.0002, 'lon' => 4.0000, 'ele' => 10.5, 'timestamp' => 8],
            ['lat' => 51.0004, 'lon' => 4.0000, 'ele' => 45.0, 'timestamp' => 16],
            ['lat' => 51.0006, 'lon' => 4.0000, 'ele' => 11.0, 'timestamp' => 24],
            ['lat' => 51.0008, 'lon' => 4.0000, 'ele' => 11.5, 'timestamp' => 32],
        ];
    }
}
