<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\InvalidDashboardLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class DashboardLayoutTest extends TestCase
{
    public function testFromArrayWhenEmpty(): void
    {
        $this->assertEquals(
            DashboardLayout::fromArray(null),
            DashboardLayout::fromArray(DashboardLayout::default()),
        );
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testFromYmlStringItShouldThrow(array $yml, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        DashboardLayout::fromArray($yml);
    }

    public static function provideInvalidConfig(): iterable
    {
        $yml = self::getValidYml();
        unset($yml[0]['widget']);
        yield 'missing "widget" key' => [$yml, '"widget" property is required for each dashboard widget'];

        $yml = self::getValidYml();
        unset($yml[0]['width']);
        yield 'missing "width" key' => [$yml, '"width" property is required for each dashboard widget'];

        $yml = self::getValidYml();
        $yml[0]['width'] = '33';
        yield 'invalid "width" key' => [$yml, '"width" property must be a valid integer'];

        $yml = self::getValidYml();
        $yml[0]['width'] = 20;
        yield 'invalid "width" key case 2' => [$yml, '"width" property must be one of [33, 50, 66, 100], found 20'];

        $yml = self::getValidYml();
        $yml[0]['config']['test'] = new \stdClass();
        yield 'invalid "config"' => [$yml, 'Invalid type for config item "test" in widget "mostRecentActivities". Expected int, string, float, bool or array.'];

        $yml = self::getValidYml();
        $yml[0]['config'] = 'lol';
        yield 'invalid "config" case 2' => [$yml, '"config" property must be an array'];
    }

    private static function getValidYml(): array
    {
        return Yaml::parse(<<<YML
- {'widget': 'mostRecentActivities', 'width': 66}
- {'widget': 'introText', 'width': 33}
- {'widget': 'weeklyStats', 'width': 100}
- {'widget': 'peakPowerOutputs', 'width': 50}
- {'widget': 'heartRateZones', 'width': 50}
- {'widget': 'activityGrid', 'width': 100}
- {'widget': 'trainingLoad', 'width': 100}
- {'widget': 'weekdayStats', 'width': 50}
- {'widget': 'dayTimeStats', 'width': 50}
- {'widget': 'distanceBreakdown', 'width': 100}
- {'widget': 'bestEfforts', 'width': 100}
- {'widget': 'yearlyDistances', 'width': 100}
- {'widget': 'challengeConsistency', 'width': 50}
- {'widget': 'ftpHistory', 'width': 50}
YML
        );
    }
}
