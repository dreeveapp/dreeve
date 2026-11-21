<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Lap;

use App\Domain\Activity\Lap\ActivityLapId;
use App\Domain\Activity\Lap\ActivityLapPowerCalculator;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Infrastructure\Measurement\Length\Meter;
use PHPUnit\Framework\TestCase;

class ActivityLapPowerCalculatorTest extends TestCase
{
    public function testItCalculatesTheAverageAndMaxPowerPerLap(): void
    {
        $laps = ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withDistance(Meter::from(1000))
                ->build(),
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-2'))
                ->withDistance(Meter::from(1000))
                ->build(),
        ]);

        $powers = ActivityLapPowerCalculator::calculate(
            laps: $laps,
            distanceStream: [0, 250, 500, 750, 1000, 1250, 1500, 1750, 2000],
            wattsStream: [100, 200, 300, 400, 500, 600, 700, 800, 900],
        );

        $firstLap = $powers->forLap(ActivityLapId::fromUnprefixed('lap-1'));
        $this->assertNotNull($firstLap);
        $this->assertSame(250, $firstLap->getAverageWatts());
        $this->assertSame(400, $firstLap->getMaxWatts());

        $secondLap = $powers->forLap(ActivityLapId::fromUnprefixed('lap-2'));
        $this->assertNotNull($secondLap);
        $this->assertSame(650, $secondLap->getAverageWatts());
        $this->assertSame(800, $secondLap->getMaxWatts());
    }

    public function testItIgnoresNullWattsButKeepsZero(): void
    {
        $laps = ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withDistance(Meter::from(1000))
                ->build(),
        ]);

        $powers = ActivityLapPowerCalculator::calculate(
            laps: $laps,
            distanceStream: [0, 250, 500, 750],
            wattsStream: [null, 0, 200, null],
        );

        $lap = $powers->forLap(ActivityLapId::fromUnprefixed('lap-1'));
        $this->assertNotNull($lap);
        $this->assertSame(100, $lap->getAverageWatts());
        $this->assertSame(200, $lap->getMaxWatts());
    }

    public function testItReturnsNoPowerWhenThereAreNoStreams(): void
    {
        $laps = ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withDistance(Meter::from(1000))
                ->build(),
        ]);

        $powers = ActivityLapPowerCalculator::calculate(
            laps: $laps,
            distanceStream: [],
            wattsStream: [],
        );

        $this->assertTrue($powers->isEmpty());
    }

    public function testItReturnsNullPowerForLapsWithoutSamples(): void
    {
        $laps = ActivityLaps::fromArray([
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-1'))
                ->withDistance(Meter::from(1000))
                ->build(),
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('lap-2'))
                ->withDistance(Meter::from(1000))
                ->build(),
        ]);

        $powers = ActivityLapPowerCalculator::calculate(
            laps: $laps,
            distanceStream: [0, 250, 500, 750],
            wattsStream: [null, null, null, null],
        );

        $firstLap = $powers->forLap(ActivityLapId::fromUnprefixed('lap-1'));
        $this->assertNotNull($firstLap);
        $this->assertNull($firstLap->getAverageWatts());
        $this->assertNull($firstLap->getMaxWatts());
        $this->assertFalse($firstLap->hasPower());

        $secondLap = $powers->forLap(ActivityLapId::fromUnprefixed('lap-2'));
        $this->assertNotNull($secondLap);
        $this->assertNull($secondLap->getAverageWatts());
    }
}
