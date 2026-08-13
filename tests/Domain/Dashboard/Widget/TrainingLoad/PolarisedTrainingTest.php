<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZonesForRollingWindow;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTraining;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZoneShare;
use App\Domain\Dashboard\Widget\TrainingLoad\ZoneDistributionTrend;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class PolarisedTrainingTest extends ContainerTestCase
{
    use MatchesSnapshots;

    /**
     * @param array<int, ZoneDistributionTrend> $expectedTrends
     */
    #[DataProvider(methodName: 'fromRollingWindowProvider')]
    public function testFromRollingWindow(
        TimeInHeartRateZones $current,
        TimeInHeartRateZones $asOfPreviousDay,
        array $expectedTrends,
    ): void {
        $shares = PolarisedTraining::fromRollingWindow(TimeInHeartRateZonesForRollingWindow::create(
            current: $current,
            asOfPreviousDay: $asOfPreviousDay,
        ))->getShares();

        self::assertSame(
            $expectedTrends,
            array_map(fn (PolarisedTrainingZoneShare $share): ZoneDistributionTrend => $share->getTrend(), $shares),
        );

        $this->assertMatchesJsonSnapshot(array_map(fn (PolarisedTrainingZoneShare $share): array => [
            'zone' => $share->getZone()->name,
            'percentage' => $share->getPercentage(),
            'trend' => $share->getTrend()->name,
            'textColor' => $share->getTextColor(),
        ], $shares));
    }

    public static function fromRollingWindowProvider(): iterable
    {
        $steady = [ZoneDistributionTrend::STEADY, ZoneDistributionTrend::STEADY, ZoneDistributionTrend::STEADY];

        yield 'both windows empty' => [
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            $steady,
        ];

        yield 'previous window empty, so there is nothing to compare against' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            $steady,
        ];

        yield 'current window empty, so everything aged out overnight' => [
            TimeInHeartRateZones::create(0, 0, 0, 0, 0),
            TimeInHeartRateZones::create(0, 8000, 1200, 800, 0),
            $steady,
        ];

        yield 'identical windows' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            $steady,
        ];

        yield 'a hard session pushes low down and moderate and high up' => [
            TimeInHeartRateZones::create(0, 7681, 1344, 975, 0),
            TimeInHeartRateZones::create(0, 8000, 1200, 800, 0),
            [ZoneDistributionTrend::DOWN, ZoneDistributionTrend::UP, ZoneDistributionTrend::UP],
        ];

        yield 'a change smaller than the rendered precision' => [
            TimeInHeartRateZones::create(0, 768100, 134400, 97500, 0),
            TimeInHeartRateZones::create(0, 768102, 134400, 97498, 0),
            $steady,
        ];

        yield 'rounding lifts the high zone into its recommended range' => [
            TimeInHeartRateZones::create(0, 800004, 100000, 99996, 0),
            TimeInHeartRateZones::create(0, 800004, 100000, 99996, 0),
            $steady,
        ];

        yield 'every zone sits exactly on a recommended boundary' => [
            TimeInHeartRateZones::create(0, 7500, 500, 2000, 0),
            TimeInHeartRateZones::create(0, 7500, 500, 2000, 0),
            $steady,
        ];
    }
}
