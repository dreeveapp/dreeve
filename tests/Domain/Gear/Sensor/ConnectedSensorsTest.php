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

        $this->assertSame(
            [[
                'manufacturer' => 1,
                'product' => 3592,
                'serialNumber' => 3485049140,
                'name' => null,
                'sensorTypes' => ['bikeRadar', 'bikeLight'],
            ]],
            $connectedSensors->jsonSerialize()
        );
    }

    public function testItKeepsDistinctHardwareApart(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3578, 3485828557, 'Garmin Rally 200', SensorType::POWER_METER),
            ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
        );

        $this->assertSame(
            ['Garmin Rally 200', null],
            array_map(static fn (ConnectedSensor $sensor): ?string => $sensor->getName(), iterator_to_array($connectedSensors))
        );
    }

    public function testItFillsInFieldsMissingFromOneOfTheMergedRows(): void
    {
        $connectedSensors = ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, null, 3485049140, null, SensorType::BIKE_LIGHT),
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR),
        );

        $this->assertSame(
            [[
                'manufacturer' => 1,
                'product' => 3592,
                'serialNumber' => 3485049140,
                'name' => 'Garmin Varia',
                'sensorTypes' => ['bikeRadar', 'bikeLight'],
            ]],
            $connectedSensors->jsonSerialize()
        );
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

    public function testASetWithoutSensorsNeverHasAnyOfThem(): void
    {
        $this->assertSame([], ConnectedSensors::fromSensors()->jsonSerialize());
        $this->assertFalse(ConnectedSensors::fromSensors()->hasAnyOf(SensorType::POWER_METER));
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

    public function testItDropsSensorTypesItNoLongerKnows(): void
    {
        $connectedSensors = ConnectedSensors::fromArray([
            ['manufacturer' => 1, 'product' => 3578, 'serialNumber' => 1, 'name' => null, 'sensorTypes' => ['powerMeter', 'somethingRemoved']],
            ['manufacturer' => 1, 'product' => 1, 'serialNumber' => 2, 'name' => null, 'sensorTypes' => ['onlyRemovedOnes']],
        ]);

        $this->assertTrue($connectedSensors->hasAnyOf(SensorType::POWER_METER));
        $this->assertCount(1, iterator_to_array($connectedSensors));
    }

    public function testItIgnoresRecordsWithoutAManufacturer(): void
    {
        $this->assertSame([], ConnectedSensors::fromArray([['sensorTypes' => ['powerMeter']]])->jsonSerialize());
    }
}
