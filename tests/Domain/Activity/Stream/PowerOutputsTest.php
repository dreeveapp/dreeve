<?php

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\Stream\PowerOutput;
use App\Domain\Activity\Stream\PowerOutputs;
use App\Infrastructure\Measurement\Mass\Kilogram;
use PHPUnit\Framework\TestCase;

class PowerOutputsTest extends TestCase
{
    public function testFromBestAveragesKeepsOnlyTheRedactedTimeIntervalsItHasAnAverageFor(): void
    {
        $powerOutputs = PowerOutputs::fromBestAverages(
            bestAverages: [1 => 540, 5 => 400, 15 => 442, 60 => 350, 1200 => 250],
            athleteWeight: Kilogram::from(80),
        );

        $this->assertEquals(
            [5, 60, 1200],
            array_map(fn (PowerOutput $powerOutput): int => $powerOutput->getTimeIntervalInSeconds(), $powerOutputs->toArray()),
        );
        $this->assertEquals(
            ['5 s', '1 m', '20 m'],
            array_map(fn (PowerOutput $powerOutput): string => $powerOutput->getFormattedTimeInterval(), $powerOutputs->toArray()),
        );
        $this->assertEquals(5.0, $powerOutputs->getFirst()->getRelativePower());
    }

    public function testFromBestAveragesWithoutAthleteWeight(): void
    {
        $powerOutputs = PowerOutputs::fromBestAverages(
            bestAverages: [5 => 400],
            athleteWeight: Kilogram::zero(),
        );

        $this->assertEquals(0, $powerOutputs->getFirst()->getRelativePower());
    }

    public function testFromBestAveragesWithoutAnyRedactedInterval(): void
    {
        $this->assertTrue(PowerOutputs::fromBestAverages(
            bestAverages: [1 => 540],
            athleteWeight: Kilogram::from(80),
        )->isEmpty());
    }
}
