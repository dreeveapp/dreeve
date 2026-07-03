<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoal;
use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoalPeriod;
use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoals;
use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoalType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrainingGoalsTest extends TestCase
{
    public function testFromConfig(): void
    {
        $this->assertEquals(
            TrainingGoals::empty(),
            TrainingGoals::fromConfig([])
        );

        $this->assertEquals(
            TrainingGoals::fromArray([
                TrainingGoal::create(
                    label: 'Cycling',
                    type: TrainingGoalType::DISTANCE,
                    period: TrainingGoalPeriod::WEEKLY,
                    goal: 200,
                    unit: 'km',
                    sportTypesToInclude: SportTypes::fromArray([SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE]),
                ),
                TrainingGoal::create(
                    label: 'Cycling',
                    type: TrainingGoalType::ELEVATION,
                    period: TrainingGoalPeriod::WEEKLY,
                    goal: 7500,
                    unit: 'm',
                    sportTypesToInclude: SportTypes::fromArray([SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE]),
                ),
                TrainingGoal::create(
                    label: 'Cycling',
                    type: TrainingGoalType::MOVING_TIME,
                    period: TrainingGoalPeriod::WEEKLY,
                    goal: 7500,
                    unit: 'hour',
                    sportTypesToInclude: SportTypes::fromArray([SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::VIRTUAL_RIDE]),
                ),
            ]),
            TrainingGoals::fromConfig(self::getValidYml())
        );
    }

    public function testFromConfigWithRestrictToDateRange(): void
    {
        $yml = self::getValidYml();
        $yml['weekly'][0]['restrictToDateRange'] = ['from' => '2026-01-01', 'to' => '2026-03-31'];

        $goals = TrainingGoals::fromConfig($yml);
        $goal = $goals->getFirst();

        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2026-01-01')));
        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2026-02-15')));
        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2026-03-31')));
        $this->assertFalse($goal->isActiveOn(SerializableDateTime::fromString('2025-12-31')));
        $this->assertFalse($goal->isActiveOn(SerializableDateTime::fromString('2026-04-01')));

        $goal = $goals->toArray()[1];
        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2026-01-01')));
        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2099-12-31')));
    }

    public function testFromConfigTreatsEmptyDateRangeAsNoRestriction(): void
    {
        // A form always submits the date inputs, even when left blank.
        $yml = self::getValidYml();
        $yml['weekly'][0]['restrictToDateRange'] = ['from' => '', 'to' => ''];

        $goal = TrainingGoals::fromConfig($yml)->getFirst();

        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2020-01-01')));
        $this->assertTrue($goal->isActiveOn(SerializableDateTime::fromString('2099-12-31')));
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testFromConfigurationItShouldThrow(array $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        TrainingGoals::fromConfig($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        $yml = self::getValidYml();
        $yml['lol'][0] = 'lol';
        yield 'invalid period provided' => [$yml, '"lol" is not a valid goal period'];

        $yml = self::getValidYml();
        $yml['weekly'][0] = 'lol';
        yield 'invalid configuration provided' => [$yml, 'Invalid TrainingGoals configuration provided'];

        $yml = self::getValidYml();
        unset($yml['weekly'][0]['label']);
        yield 'missing "label" key' => [$yml, '"label" property is required'];

        $yml = self::getValidYml();
        unset($yml['weekly'][0]['type']);
        yield 'missing "type" key' => [$yml, '"type" property is required'];

        $yml = self::getValidYml();
        unset($yml['weekly'][0]['unit']);
        yield 'missing "unit" key' => [$yml, '"unit" property is required'];

        $yml = self::getValidYml();
        unset($yml['weekly'][0]['goal']);
        yield 'missing "goal" key' => [$yml, '"goal" property is required'];

        $yml = self::getValidYml();
        unset($yml['weekly'][0]['sportTypesToInclude']);
        yield 'missing "sportTypesToInclude" key' => [$yml, '"sportTypesToInclude" property is required'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['label'] = '';
        yield 'empty "label"' => [$yml, '"label" property cannot be empty'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['goal'] = 'LOL';
        yield 'invalid "goal"' => [$yml, '"goal" property must be a valid number'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['type'] = 'LOL';
        yield 'invalid "type"' => [$yml, '"LOL" is not a valid goalType'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['sportTypesToInclude'] = 'LOL';
        yield 'invalid "sportTypesToInclude"' => [$yml, '"sportTypesToInclude" property must be an array'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['sportTypesToInclude'] = [];
        yield 'empty "sportTypesToInclude"' => [$yml, '"sportTypesToInclude" property cannot be empty'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['sportTypesToInclude'] = ['test'];
        yield 'invalid sport type in "sportTypesToInclude"' => [$yml, '"test" is not a valid sport type'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['type'] = 'distance';
        $yml['weekly'][0]['unit'] = 'minute';
        yield 'invalid "type and unit" combo' => [$yml, 'The unit "minute" is not valid for goal type "distance"'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['type'] = 'movingTime';
        $yml['weekly'][0]['unit'] = 'km';
        yield 'invalid "type and unit" combo 2' => [$yml, 'The unit "km" is not valid for goal type "movingTime"'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['restrictToDateRange'] = ['from' => '2026-01-01'];
        yield 'missing "to" in "restrictToDateRange"' => [$yml, '"restrictToDateRange" requires both "from" and "to" keys'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['restrictToDateRange'] = ['from' => 'not-a-date', 'to' => '2026-03-31'];
        yield 'invalid date in "restrictToDateRange"' => [$yml, '"restrictToDateRange" contains invalid date values, expected format "YYYY-MM-DD"'];

        $yml = self::getValidYml();
        $yml['weekly'][0]['restrictToDateRange'] = ['from' => '2026-06-01', 'to' => '2026-03-31'];
        yield '"from" after "to" in "restrictToDateRange"' => [$yml, '"restrictToDateRange.from" must be before or equal to "restrictToDateRange.to"'];
    }

    private static function getValidYml(): array
    {
        return [
            'weekly' => [
                [
                    'label' => 'Cycling',
                    'type' => 'distance',
                    'unit' => 'km',
                    'goal' => 200,
                    'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
                ],
                [
                    'label' => 'Cycling',
                    'type' => 'elevation',
                    'unit' => 'm',
                    'goal' => 7500,
                    'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
                ],
                [
                    'label' => 'Cycling',
                    'type' => 'movingTime',
                    'unit' => 'hour',
                    'goal' => 7500,
                    'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
                ],
            ],
        ];
    }
}
