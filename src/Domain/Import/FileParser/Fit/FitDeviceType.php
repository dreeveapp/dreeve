<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

use App\Domain\Gear\Sensor\SensorType;

final class FitDeviceType
{
    private const int SOURCE_TYPE_ANTPLUS = 1;
    private const int SOURCE_TYPE_BLUETOOTH_LOW_ENERGY = 3;

    private const int ANTPLUS_BIKE_POWER = 11;
    private const int ANTPLUS_ENVIRONMENT_SENSOR_LEGACY = 12;
    private const int ANTPLUS_MULTI_SPORT_SPEED_DISTANCE = 15;
    private const int ANTPLUS_FITNESS_EQUIPMENT = 17;
    private const int ANTPLUS_ENV_SENSOR = 25;
    private const int ANTPLUS_MUSCLE_OXYGEN = 31;
    private const int ANTPLUS_SHIFTING = 34;
    private const int ANTPLUS_BIKE_LIGHT_MAIN = 35;
    private const int ANTPLUS_BIKE_LIGHT_SHARED = 36;
    private const int ANTPLUS_BIKE_RADAR = 40;
    private const int ANTPLUS_BIKE_AERO = 46;
    private const int ANTPLUS_HEART_RATE = 120;
    private const int ANTPLUS_BIKE_SPEED_CADENCE = 121;
    private const int ANTPLUS_BIKE_CADENCE = 122;
    private const int ANTPLUS_BIKE_SPEED = 123;
    private const int ANTPLUS_STRIDE_SPEED_DISTANCE = 124;

    private const int BLE_HEART_RATE = 1;
    private const int BLE_BIKE_POWER = 2;
    private const int BLE_BIKE_SPEED_CADENCE = 3;
    private const int BLE_BIKE_SPEED = 4;
    private const int BLE_BIKE_CADENCE = 5;
    private const int BLE_FOOTPOD = 6;
    private const int BLE_BIKE_TRAINER = 7;

    /**
     * @param array<string, mixed> $deviceInfoFields
     */
    public static function resolveSensorType(array $deviceInfoFields): ?SensorType
    {
        if (null !== $antPlusDeviceType = self::readInt($deviceInfoFields, 'antplus_device_type')) {
            return self::fromAntPlus($antPlusDeviceType);
        }
        if (null !== $bleDeviceType = self::readInt($deviceInfoFields, 'ble_device_type')) {
            return self::fromBluetoothLowEnergy($bleDeviceType);
        }
        if (null !== self::readInt($deviceInfoFields, 'local_device_type')) {
            return null;
        }
        if (null !== self::readInt($deviceInfoFields, 'ant_device_type')) {
            return null;
        }

        $deviceType = self::readInt($deviceInfoFields, 'device_type');
        if (null === $deviceType) {
            return null;
        }

        return match (self::readInt($deviceInfoFields, 'source_type')) {
            self::SOURCE_TYPE_ANTPLUS => self::fromAntPlus($deviceType),
            self::SOURCE_TYPE_BLUETOOTH_LOW_ENERGY => self::fromBluetoothLowEnergy($deviceType),
            default => null,
        };
    }

    private static function fromAntPlus(int $deviceType): ?SensorType
    {
        return match ($deviceType) {
            self::ANTPLUS_HEART_RATE => SensorType::HEART_RATE_MONITOR,
            self::ANTPLUS_BIKE_POWER => SensorType::POWER_METER,
            self::ANTPLUS_BIKE_SPEED_CADENCE => SensorType::SPEED_CADENCE_SENSOR,
            self::ANTPLUS_BIKE_SPEED => SensorType::SPEED_SENSOR,
            self::ANTPLUS_BIKE_CADENCE => SensorType::CADENCE_SENSOR,
            self::ANTPLUS_STRIDE_SPEED_DISTANCE, self::ANTPLUS_MULTI_SPORT_SPEED_DISTANCE => SensorType::FOOT_POD,
            self::ANTPLUS_FITNESS_EQUIPMENT => SensorType::SMART_TRAINER,
            self::ANTPLUS_MUSCLE_OXYGEN => SensorType::MUSCLE_OXYGEN_SENSOR,
            self::ANTPLUS_SHIFTING => SensorType::ELECTRONIC_SHIFTING,
            self::ANTPLUS_BIKE_RADAR => SensorType::BIKE_RADAR,
            self::ANTPLUS_BIKE_LIGHT_MAIN, self::ANTPLUS_BIKE_LIGHT_SHARED => SensorType::BIKE_LIGHT,
            self::ANTPLUS_ENV_SENSOR, self::ANTPLUS_ENVIRONMENT_SENSOR_LEGACY => SensorType::TEMPERATURE_SENSOR,
            self::ANTPLUS_BIKE_AERO => SensorType::AERO_SENSOR,
            default => null,
        };
    }

    private static function fromBluetoothLowEnergy(int $deviceType): ?SensorType
    {
        return match ($deviceType) {
            self::BLE_HEART_RATE => SensorType::HEART_RATE_MONITOR,
            self::BLE_BIKE_POWER => SensorType::POWER_METER,
            self::BLE_BIKE_SPEED_CADENCE => SensorType::SPEED_CADENCE_SENSOR,
            self::BLE_BIKE_SPEED => SensorType::SPEED_SENSOR,
            self::BLE_BIKE_CADENCE => SensorType::CADENCE_SENSOR,
            self::BLE_FOOTPOD => SensorType::FOOT_POD,
            self::BLE_BIKE_TRAINER => SensorType::SMART_TRAINER,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function readInt(array $fields, string $key): ?int
    {
        return is_numeric($fields[$key] ?? null) ? (int) round((float) $fields[$key]) : null;
    }
}
