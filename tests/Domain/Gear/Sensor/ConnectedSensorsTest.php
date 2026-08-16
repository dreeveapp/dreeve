<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Sensor;

use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\SensorType;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class ConnectedSensorsTest extends TestCase
{
    public function testItMergesTheFunctionsOfOnePhysicalSensor(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3592, 3485049140, null, SensorType::BIKE_LIGHT),
            ConnectedSensor::create(1, 3592, 3485049140, null, SensorType::BIKE_RADAR),
        );

        $this->assertCount(1, $connectedSensors);
        $this->assertSame(
            [SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT],
            $connectedSensors->getSensorTypes()
        );
    }

    public function testItKeepsDistinctHardwareApart(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3578, 3485828557, 'Garmin Rally 200', SensorType::POWER_METER),
            ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
        );

        $this->assertCount(2, $connectedSensors);
    }

    public function testItFillsInFieldsMissingFromOneOfTheMergedRows(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, null, 3485049140, null, SensorType::BIKE_LIGHT),
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR),
        );

        $sensor = iterator_to_array($connectedSensors)[0];
        $this->assertSame(3592, $sensor->getProduct());
        $this->assertSame('Garmin Varia', $sensor->getName());
    }

    public function testHasAnyOf(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3578, 3485828557, null, SensorType::POWER_METER),
        );

        $this->assertTrue($connectedSensors->hasAnyOf(SensorType::POWER_METER));
        $this->assertTrue($connectedSensors->hasAnyOf(SensorType::HEART_RATE_MONITOR, SensorType::POWER_METER));
        $this->assertFalse($connectedSensors->hasAnyOf(SensorType::HEART_RATE_MONITOR));
    }

    public function testAnEmptySetNeverHasAnySensor(): void
    {
        $this->assertTrue(ConnectedSensors::empty()->isEmpty());
        $this->assertFalse(ConnectedSensors::empty()->hasAnyOf(SensorType::POWER_METER));
    }

    public function testItRoundTripsThroughJson(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT),
            ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
        );

        $this->assertEquals(
            $connectedSensors,
            ConnectedSensors::fromArray(Json::decode(Json::encode($connectedSensors)))
        );
    }

    public function testItSerializesToTheStoredShape(): void
    {
        $this->assertSame(
            [[
                'manufacturer' => 123,
                'product' => 3,
                'serialNumber' => 550082112,
                'name' => null,
                'sensorTypes' => ['heartRateMonitor'],
            ]],
            ConnectedSensors::fromSensors(
                ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
            )->jsonSerialize()
        );
    }

    public function testItDropsSensorTypesItNoLongerKnows(): void
    {
        $connectedSensors = ConnectedSensors::fromArray([
            ['manufacturer' => 1, 'product' => 3578, 'serialNumber' => 1, 'name' => null, 'sensorTypes' => ['powerMeter', 'somethingRemoved']],
            ['manufacturer' => 1, 'product' => 1, 'serialNumber' => 2, 'name' => null, 'sensorTypes' => ['onlyRemovedOnes']],
        ]);

        $this->assertCount(1, $connectedSensors);
        $this->assertSame([SensorType::POWER_METER], $connectedSensors->getSensorTypes());
    }

    public function testItIgnoresRecordsWithoutAManufacturer(): void
    {
        $this->assertTrue(
            ConnectedSensors::fromArray([['sensorTypes' => ['powerMeter']]])->isEmpty()
        );
    }
}
