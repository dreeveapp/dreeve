<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Gpx;

use App\Domain\Import\FileParser\Gpx\GpxWorkoutSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GpxWorkoutSummaryTest extends TestCase
{
    private const string REAL_WORLD_SUMMARY = 'Workout(id=182775413781138, uuid=68cfcda9-fa21-4a95-b2a1-fcf228705550, start=1785743210127, end=1785746832700, activeDuration=3512996, comment=, distance=4995.375465210002, repetitions=0, energy=1696923.2514277645, workoutTypeId=walking, intervalSetUsedId=-1)';

    public function testTryFromStringConvertsUnits(): void
    {
        $summary = GpxWorkoutSummary::tryFromString(self::REAL_WORLD_SUMMARY);

        $this->assertNotNull($summary);
        // (1785746832700 - 1785743210127) ms
        $this->assertSame(3623, $summary->getElapsedTimeInSeconds());
        // 3512996 ms
        $this->assertSame(3513, $summary->getMovingTimeInSeconds());
        $this->assertSame(4995.375465210002, $summary->getDistanceInMeter());
        // 1696923.25 joule / 4184
        $this->assertSame(406, $summary->getCalories());
        // "comment=" carries no information.
        $this->assertNull($summary->getDescription());
    }

    public function testTryFromStringReadsComment(): void
    {
        $summary = GpxWorkoutSummary::tryFromString('Workout(start=1785743210127, comment=Walked to the market, distance=4995.375465210002)');

        $this->assertNotNull($summary);
        $this->assertSame('Walked to the market', $summary->getDescription());
    }

    public function testTryFromStringKeepsCommasAndParenthesesInsideValues(): void
    {
        $summary = GpxWorkoutSummary::tryFromString('Workout(start=1785743210127, comment=Ran fast, then walked (slowly), distance=1000)');

        $this->assertNotNull($summary);
        $this->assertSame('Ran fast, then walked (slowly)', $summary->getDescription());
        $this->assertSame(1000.0, $summary->getDistanceInMeter());
    }

    #[DataProvider('provideNonSummaries')]
    public function testTryFromStringReturnsNullForNonSummaries(string $value): void
    {
        $this->assertNull(GpxWorkoutSummary::tryFromString($value));
    }

    public static function provideNonSummaries(): array
    {
        return [
            'empty' => [''],
            'plain name' => ['Morning Ride'],
            'no parentheses' => ['Workout'],
            'no fields' => ['Workout()'],
            'name that looks like a constructor' => ['Workout(hard)'],
            'name with parentheses' => ['Ride (with Bob)'],
            'another entity' => ['Route(start=1785743210127)'],
            'unknown fields only' => ['Workout(id=1, uuid=68cfcda9)'],
        ];
    }

    #[DataProvider('provideImplausibleValues')]
    public function testTryFromStringDegradesPerField(string $summaryString, string $getter): void
    {
        $summary = GpxWorkoutSummary::tryFromString($summaryString);

        $this->assertNotNull($summary);
        $this->assertNull($summary->{$getter}());
    }

    public static function provideImplausibleValues(): array
    {
        return [
            'non numeric distance' => ['Workout(distance=abc)', 'getDistanceInMeter'],
            'negative distance' => ['Workout(distance=-5)', 'getDistanceInMeter'],
            'zero distance' => ['Workout(distance=0)', 'getDistanceInMeter'],
            'earth sized distance' => ['Workout(distance=99999999)', 'getDistanceInMeter'],
            'non numeric energy' => ['Workout(distance=1000, energy=NaN)', 'getCalories'],
            'zero energy' => ['Workout(distance=1000, energy=0)', 'getCalories'],
            'negative energy' => ['Workout(distance=1000, energy=-5)', 'getCalories'],
            'implausibly large energy' => ['Workout(distance=1000, energy=999999999)', 'getCalories'],
            'missing energy' => ['Workout(distance=1000)', 'getCalories'],
            'empty active duration' => ['Workout(distance=1000, activeDuration=)', 'getMovingTimeInSeconds'],
            'zero active duration' => ['Workout(activeDuration=0)', 'getMovingTimeInSeconds'],
            'end before start' => ['Workout(start=1785746832700, end=1785743210127)', 'getElapsedTimeInSeconds'],
            'missing end' => ['Workout(start=1785743210127)', 'getElapsedTimeInSeconds'],
            'timestamps in seconds' => ['Workout(start=1785743210, end=1785746832)', 'getElapsedTimeInSeconds'],
            'elapsed time over a week' => ['Workout(start=1785743210127, end=1786743210127)', 'getElapsedTimeInSeconds'],
        ];
    }
}
