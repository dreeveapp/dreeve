<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Lap;

use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Lap\ActivityLapId;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Domain\Activity\Lap\ActivityLapsSummary;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use PHPUnit\Framework\TestCase;

class ActivityLapsSummaryTest extends TestCase
{
    public function testItSummarizesLaps(): void
    {
        $summary = ActivityLapsSummary::create(ActivityLaps::fromArray([
            $this->buildLap(lapNumber: 1, distanceInMeter: 1000, averageSpeed: 10, movingTimeInSeconds: 100),
            $this->buildLap(lapNumber: 2, distanceInMeter: 2000, averageSpeed: 12, movingTimeInSeconds: 120),
            $this->buildLap(lapNumber: 3, distanceInMeter: 500, averageSpeed: 20, movingTimeInSeconds: 60),
        ]));

        $this->assertSame(3, $summary->getNumberOfLaps());
        $this->assertSame(2, $summary->getNumberOfValidLaps());
        $this->assertEqualsWithDelta(3.5, $summary->getTotalDistanceInKilometer()->toFloat(), 0.001);
        $this->assertEqualsWithDelta(1.5, $summary->getAverageLapDistanceInKilometer()?->toFloat(), 0.001);
        $this->assertSame(2, $summary->getFastestLap()?->getLapNumber());
        $this->assertEqualsWithDelta(9.1, $summary->getRegularityPercentage(), 0.001);
    }

    public function testItReturnsEmptyStatsForEmptyLaps(): void
    {
        $summary = ActivityLapsSummary::create(ActivityLaps::empty());

        $this->assertSame(0, $summary->getNumberOfLaps());
        $this->assertSame(0, $summary->getNumberOfValidLaps());
        $this->assertEqualsWithDelta(0.0, $summary->getTotalDistanceInKilometer()->toFloat(), 0.001);
        $this->assertNull($summary->getAverageLapDistanceInKilometer());
        $this->assertNull($summary->getFastestLap());
        $this->assertNull($summary->getRegularityPercentage());
    }

    public function testItIgnoresLapsShorterThanOneKilometer(): void
    {
        $summary = ActivityLapsSummary::create(ActivityLaps::fromArray([
            $this->buildLap(lapNumber: 1, distanceInMeter: 999, averageSpeed: 30, movingTimeInSeconds: 10),
            $this->buildLap(lapNumber: 2, distanceInMeter: 1000, averageSpeed: 10, movingTimeInSeconds: 100),
        ]));

        $this->assertSame(2, $summary->getNumberOfLaps());
        $this->assertSame(1, $summary->getNumberOfValidLaps());
        $this->assertEqualsWithDelta(1.0, $summary->getAverageLapDistanceInKilometer()?->toFloat(), 0.001);
        $this->assertSame(2, $summary->getFastestLap()?->getLapNumber());
        // Regularity needs at least two valid laps.
        $this->assertNull($summary->getRegularityPercentage());
    }

    public function testItReturnsNullForTheFastestLapWhenThereAreNoValidLaps(): void
    {
        $summary = ActivityLapsSummary::create(ActivityLaps::fromArray([
            $this->buildLap(lapNumber: 1, distanceInMeter: 79, averageSpeed: 30, movingTimeInSeconds: 9),
        ]));

        $this->assertNull($summary->getFastestLap());
        $this->assertNull($summary->getAverageLapDistanceInKilometer());
    }

    private function buildLap(
        int $lapNumber,
        int $distanceInMeter,
        float $averageSpeed,
        int $movingTimeInSeconds,
    ): ActivityLap {
        return ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('lap-'.$lapNumber))
            ->withLapNumber($lapNumber)
            ->withDistance(Meter::from($distanceInMeter))
            ->withAverageSpeed(MetersPerSecond::from($averageSpeed))
            ->withMovingTimeInSeconds($movingTimeInSeconds)
            ->build();
    }
}
