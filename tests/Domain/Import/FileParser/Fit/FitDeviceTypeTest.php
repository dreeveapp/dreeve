<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Gear\Sensor\SensorType;
use App\Domain\Import\FileParser\Fit\FitDeviceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FitDeviceTypeTest extends TestCase
{
    #[DataProvider('provideDeviceInfoFields')]
    public function testResolveSensorType(array $deviceInfoFields, ?SensorType $expectedSensorType): void
    {
        $this->assertSame($expectedSensorType, FitDeviceType::resolveSensorType($deviceInfoFields));
    }

    public static function provideDeviceInfoFields(): iterable
    {
        yield 'ant+ heart rate' => [['source_type' => 1, 'device_type' => 120], SensorType::HEART_RATE_MONITOR];
        yield 'ant+ power meter' => [['source_type' => 1, 'device_type' => 11], SensorType::POWER_METER];
        yield 'ant+ foot pod' => [['source_type' => 1, 'device_type' => 124], SensorType::FOOT_POD];
        yield 'ant+ multi sport speed distance is also a foot pod' => [['source_type' => 1, 'device_type' => 15], SensorType::FOOT_POD];
        yield 'ant+ bike light main' => [['source_type' => 1, 'device_type' => 35], SensorType::BIKE_LIGHT];
        yield 'ant+ bike light shared' => [['source_type' => 1, 'device_type' => 36], SensorType::BIKE_LIGHT];
        yield 'ant+ bike radar' => [['source_type' => 1, 'device_type' => 40], SensorType::BIKE_RADAR];
        yield 'ant+ shifting' => [['source_type' => 1, 'device_type' => 34], SensorType::ELECTRONIC_SHIFTING];
        yield 'ant+ fitness equipment' => [['source_type' => 1, 'device_type' => 17], SensorType::SMART_TRAINER];
        yield 'ant+ environment sensor' => [['source_type' => 1, 'device_type' => 12], SensorType::TEMPERATURE_SENSOR];
        yield 'local sensor hub shares that number' => [['source_type' => 5, 'device_type' => 12], null];
        yield 'local barometer' => [['source_type' => 5, 'device_type' => 4], null];
        yield 'local wrist heart rate' => [['source_type' => 5, 'device_type' => 10], null];
        yield 'local gps' => [['source_type' => 5, 'device_type' => 0], null];
        yield 'ble heart rate' => [['source_type' => 3, 'device_type' => 1], SensorType::HEART_RATE_MONITOR];
        yield 'ble power meter' => [['source_type' => 3, 'device_type' => 2], SensorType::POWER_METER];
        yield 'ble trainer' => [['source_type' => 3, 'device_type' => 7], SensorType::SMART_TRAINER];
        yield 'ant+ antfs is not a sensor, but is heart rate over ble' => [['source_type' => 1, 'device_type' => 1], null];
        yield 'raw ant' => [['source_type' => 0, 'device_type' => 1], null];
        yield 'classic bluetooth' => [['source_type' => 2, 'device_type' => 1], null];
        yield 'wifi' => [['source_type' => 4, 'device_type' => 1], null];
        yield 'unmapped ant+ weight scale' => [['source_type' => 1, 'device_type' => 119], null];
        yield 'missing source type' => [['device_type' => 120], null];
        yield 'missing device type' => [['source_type' => 1], null];
        yield 'no fields at all' => [[], null];
        yield 'invalid sentinel' => [['source_type' => 1, 'device_type' => null], null];
        yield 'resolved ant+ subfield' => [['antplus_device_type' => 120], SensorType::HEART_RATE_MONITOR];
        yield 'resolved ble subfield' => [['ble_device_type' => 1], SensorType::HEART_RATE_MONITOR];
        yield 'resolved local subfield' => [['local_device_type' => 12], null];
        yield 'resolved raw ant subfield' => [['ant_device_type' => 1], null];
    }
}
