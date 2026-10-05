<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZonesForRollingWindow;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTraining;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZone;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZoneShare;
use App\Domain\Dashboard\Widget\TrainingLoad\ZoneDistributionTrend;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PolarisedTrainingTest extends ContainerTestCase
{
    /**
     * @param array<int, ZoneDistributionTrend> $expectedTrends
     * @param array<int, float>                 $expectedPercentages
     */
    #[DataProvider(methodName: 'fromRollingWindowProvider')]
    public function testFromRollingWindow(
        TimeInHeartRateZones $current,
        TimeInHeartRateZones $asOfPreviousDay,
        array $expectedTrends,
        array $expectedPercentages,
    ): void {
        $shares = PolarisedTraining::fromRollingWindow(TimeInHeartRateZonesForRollingWindow::create(
            current: $current,
            asOfPreviousDay: $asOfPreviousDay,
        ))->getShares();

        self::assertSame(
            $expectedTrends,
            array_map(fn (PolarisedTrainingZoneShare $share): ZoneDistributionTrend => $share->getTrend(), $shares),
        );

        self::assertSame(
            [PolarisedTrainingZone::LOW, PolarisedTrainingZone::MODERATE, PolarisedTrainingZone::HIGH],
            array_map(fn (PolarisedTrainingZoneShare $share): PolarisedTrainingZone => $share->getZone(), $shares),
        );
        self::assertSame(
            $expectedPercentages,
            array_map(fn (PolarisedTrainingZoneShare $share): float => $share->getPercentage(), $shares),
        );
    }

    public static function fromRollingWindowProvider(): iterable
    {
        $steady = [ZoneDistributionTrend::STEADY, ZoneDistributionTrend::STEADY, ZoneDistributionTrend::STEADY];

        yield 'both windows empty' => [
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            $steady,
            [0.0, 0.0, 0.0],
        ];

        yield 'previous window empty, so there is nothing to compare against' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            $steady,
            [76.81, 13.44, 9.75],
        ];

        yield 'current window empty, so everything aged out overnight' => [
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            TimeInHeartRateZones::create(0, 8000, 1200, 800, 0),
            $steady,
            [0.0, 0.0, 0.0],
        ];

        yield 'identical windows' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            $steady,
            [76.81, 13.44, 9.75],
        ];

        yield 'a hard session pushes low down and moderate and high up' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 8000, 1200, 800, 0),
            [ZoneDistributionTrend::DOWN, ZoneDistributionTrend::UP, ZoneDistributionTrend::UP],
            [76.81, 13.44, 9.75],
        ];

        yield 'a change smaller than the rendered precision' => [
            TimeInHeartRateZones::create(0, 768100, 134400, 97500, 0),
            TimeInHeartRateZones::create(0, 768102, 134400, 97498, 0),
            $steady,
            [76.81, 13.44, 9.75],
        ];

        yield 'a raw percentage that rounds up to a whole number' => [
            TimeInHeartRateZones::create(0, 800004, 100000, 99996, 0),
            TimeInHeartRateZones::create(0, 800004, 100000, 99996, 0),
            $steady,
            [80.0, 10.0, 10.0],
        ];
    }
}
