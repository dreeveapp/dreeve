<?php

namespace App\Tests\Domain\Activity\Eddington\Config;

use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;
use App\Domain\Activity\Eddington\InvalidEddingtonConfiguration;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EddingtonConfigurationTest extends TestCase
{
    public function testFromScalarArray(): void
    {
        $theConfig = EddingtonConfiguration::fromArray([
            EddingtonConfigItem::create(
                label: 'Ride',
                sportTypesToInclude: SportTypes::fromArray([
                    SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE,
                ]),
                showInDashboardWidget: true,
            ),
            EddingtonConfigItem::create(
                label: 'Run',
                sportTypesToInclude: SportTypes::fromArray([
                    SportType::RUN, SportType::TRAIL_RUN, SportType::VIRTUAL_RUN,
                ]),
                showInDashboardWidget: true,
            ),
            EddingtonConfigItem::create(
                label: 'Walk',
                sportTypesToInclude: SportTypes::fromArray([
                    SportType::WALK, SportType::HIKE,
                ]),
                showInDashboardWidget: false,
            ),
        ]);
        $this->assertEquals(
            $theConfig,
            EddingtonConfiguration::fromScalarArray(self::getValidYml()),
        );

        $this->assertEquals(
            $theConfig,
            EddingtonConfiguration::fromScalarArray([])
        );
    }

    #[DataProvider(methodName: 'provideInvalidEddingtonConfig')]
    public function testFromScalarArrayItShouldThrow(array $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidEddingtonConfiguration($expectedException));
        EddingtonConfiguration::fromScalarArray($config);
    }

    public static function provideInvalidEddingtonConfig(): iterable
    {
        $yml = self::getValidYml();
        $yml[0] = 'lol';
        yield 'invalid eddington configuration provided' => [$yml, 'Invalid Eddington configuration provided'];

        $yml = self::getValidYml();
        unset($yml[0]['label']);
        yield 'missing "label" key' => [$yml, '"label" property is required'];

        $yml = self::getValidYml();
        unset($yml[0]['sportTypesToInclude']);
        yield 'missing "sportTypesToInclude" key' => [$yml, '"sportTypesToInclude" property is required'];

        $yml = self::getValidYml();
        $yml[0]['label'] = '';
        yield 'empty "label"' => [$yml, '"label" property cannot be empty'];

        $yml = self::getValidYml();
        $yml[0]['sportTypesToInclude'] = 'LOL';
        yield 'invalid "sportTypesToInclude"' => [$yml, '"sportTypesToInclude" property must be an array'];

        $yml = self::getValidYml();
        $yml[0]['sportTypesToInclude'] = [];
        yield 'empty "sportTypesToInclude"' => [$yml, '"sportTypesToInclude" property cannot be empty'];

        $yml = self::getValidYml();
        $yml[0]['sportTypesToInclude'] = ['test'];
        yield 'invalid sport type in "sportTypesToInclude"' => [$yml, '"test" is not a valid sport type'];

        $yml = self::getValidYml();
        $yml[0]['sportTypesToInclude'] = ['Run', 'Ride'];
        yield 'mixed activity types in "sportTypesToInclude"' => [$yml, 'Eddington "Ride" contains sport types with different activity types'];

        $yml = self::getValidYml();
        $yml[0]['showInDashboardWidget'] = 'LOL';
        yield 'invalid "showInDashboardWidget"' => [$yml, '"showInDashboardWidget" property must be a boolean'];

        $yml = self::getValidYml();
        $yml[2]['showInDashboardWidget'] = true;
        yield 'too many items in showInDashboardWidget"' => [$yml, 'You can only have two Eddingtons with "showInDashboardWidget" set to true'];
    }

    private static function getValidYml(): array
    {
        return [
            [
                'label' => 'Ride',
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
                'showInDashboardWidget' => true,
            ],
            [
                'label' => 'Run',
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
                'showInDashboardWidget' => true,
            ],
            [
                'label' => 'Walk',
                'sportTypesToInclude' => ['Walk', 'Hike'],
                'showInDashboardWidget' => false,
            ],
        ];
    }
}
