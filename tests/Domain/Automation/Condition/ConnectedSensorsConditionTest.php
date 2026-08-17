<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\ConnectedSensorsCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\Sensor;
use App\Domain\Gear\Sensor\SensorRepository;
use App\Domain\Gear\Sensor\Sensors;
use App\Domain\Gear\Sensor\SensorType;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConnectedSensorsConditionTest extends TestCase
{
    private ConnectedSensorsCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'isOneOf', 'sensorTypes' => []],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sensorTypes' => ['powerMeter']]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, array $sensorTypes, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'sensorTypes' => $sensorTypes]));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatches(string $operator, array $sensorTypes, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withConnectedSensors(ConnectedSensors::fromSensors(
                ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT),
                ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
            ))
            ->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'sensorTypes' => $sensorTypes])));
    }

    #[DataProvider('provideSetOperators')]
    public function testItNeverMatchesWhenTheSensorsWereNeverCaptured(string $operator): void
    {
        $activity = ActivityBuilder::fromDefaults()->withoutConnectedSensors()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'sensorTypes' => ['powerMeter']])));
    }

    public function testItMatchesIsNoneOfWhenTheActivityIsKnownToHaveHadNoSensors(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withConnectedSensors(ConnectedSensors::fromSensors())->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'isNoneOf', 'sensorTypes' => ['powerMeter']])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sensorTypes' => ['powerMeter']])));
    }

    public function testItIsAvailableOnlyWhenSensorsWereRecorded(): void
    {
        $this->assertFalse($this->conditionFor(Sensors::empty())->isAvailable());
        $this->assertTrue($this->conditionFor(Sensors::fromArray([
            Sensor::fromState('Garmin Varia', [SensorType::BIKE_RADAR], 3),
        ]))->isAvailable());
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', ['powerMeter'], 'Invalid connected sensors operator "nope".'];
        yield 'operator that is not for a set' => ['contains', ['powerMeter'], 'Invalid connected sensors operator "contains".'];
        yield 'no sensors' => ['isOneOf', [], 'At least one sensor is required.'];
        yield 'unknown sensor' => ['isOneOf', ['nope'], 'Invalid sensor "nope".'];
    }

    public static function provideMatchExpectations(): iterable
    {
        yield 'is one of a connected sensor' => ['isOneOf', ['heartRateMonitor'], true];
        yield 'is one of several, one connected' => ['isOneOf', ['powerMeter', 'bikeRadar'], true];
        yield 'is one of a sensor that was not connected' => ['isOneOf', ['powerMeter'], false];
        yield 'is none of a sensor that was not connected' => ['isNoneOf', ['powerMeter'], true];
        yield 'is none of a connected sensor' => ['isNoneOf', ['bikeLight'], false];
    }

    public static function provideSetOperators(): iterable
    {
        yield 'isOneOf' => ['isOneOf'];
        yield 'isNoneOf' => ['isNoneOf'];
    }

    private function conditionFor(Sensors $sensors): ConnectedSensorsCondition
    {
        $sensorRepository = $this->createStub(SensorRepository::class);
        $sensorRepository->method('findAll')->willReturn($sensors);

        return new ConnectedSensorsCondition($sensorRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = $this->conditionFor(Sensors::empty());
    }
}
