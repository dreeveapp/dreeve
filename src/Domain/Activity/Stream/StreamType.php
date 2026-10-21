<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

enum StreamType: string
{
    case TIME = 'time';
    case DISTANCE = 'distance';
    case LAT_LNG = 'latlng';
    case ALTITUDE = 'altitude';
    case VELOCITY = 'velocity_smooth';
    case HEART_RATE = 'heartrate';
    case CADENCE = 'cadence';
    case WATTS = 'watts';
    case TEMP = 'temp';
    case MOVING = 'moving';
    case GRADE = 'grade_smooth';

    public function getApiKey(): string
    {
        return match ($this) {
            self::TIME => 'timeInSeconds',
            self::DISTANCE => 'distanceInMeter',
            self::LAT_LNG => 'latLng',
            self::ALTITUDE => 'altitudeInMeter',
            self::VELOCITY => 'velocityInMeterPerSecond',
            self::HEART_RATE => 'heartRate',
            self::CADENCE => 'cadence',
            self::WATTS => 'powerInWatts',
            self::TEMP => 'temperatureInCelsius',
            self::MOVING => 'moving',
            self::GRADE => 'gradeInPercent',
        };
    }

    /**
     * @return StreamType[]
     */
    public static function thatSupportDistributionValues(): array
    {
        return [
            self::WATTS, self::HEART_RATE, self::VELOCITY, self::CADENCE,
        ];
    }
}
