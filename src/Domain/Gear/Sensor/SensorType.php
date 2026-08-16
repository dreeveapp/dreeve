<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SensorType: string implements TranslatableInterface
{
    case HEART_RATE_MONITOR = 'heartRateMonitor';
    case POWER_METER = 'powerMeter';
    case SPEED_CADENCE_SENSOR = 'speedCadenceSensor';
    case SPEED_SENSOR = 'speedSensor';
    case CADENCE_SENSOR = 'cadenceSensor';
    case FOOT_POD = 'footPod';
    case SMART_TRAINER = 'smartTrainer';
    case MUSCLE_OXYGEN_SENSOR = 'muscleOxygenSensor';
    case ELECTRONIC_SHIFTING = 'electronicShifting';
    case BIKE_RADAR = 'bikeRadar';
    case BIKE_LIGHT = 'bikeLight';
    case TEMPERATURE_SENSOR = 'temperatureSensor';
    case AERO_SENSOR = 'aeroSensor';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::HEART_RATE_MONITOR => $translator->trans('Heart rate monitor', locale: $locale),
            self::POWER_METER => $translator->trans('Power meter', locale: $locale),
            self::SPEED_CADENCE_SENSOR => $translator->trans('Speed & cadence sensor', locale: $locale),
            self::SPEED_SENSOR => $translator->trans('Speed sensor', locale: $locale),
            self::CADENCE_SENSOR => $translator->trans('Cadence sensor', locale: $locale),
            self::FOOT_POD => $translator->trans('Foot pod', locale: $locale),
            self::SMART_TRAINER => $translator->trans('Smart trainer', locale: $locale),
            self::MUSCLE_OXYGEN_SENSOR => $translator->trans('Muscle oxygen sensor', locale: $locale),
            self::ELECTRONIC_SHIFTING => $translator->trans('Electronic shifting', locale: $locale),
            self::BIKE_RADAR => $translator->trans('Bike radar', locale: $locale),
            self::BIKE_LIGHT => $translator->trans('Bike light', locale: $locale),
            self::TEMPERATURE_SENSOR => $translator->trans('Temperature sensor', locale: $locale),
            self::AERO_SENSOR => $translator->trans('Aero sensor', locale: $locale),
        };
    }

    /**
     * @param SensorType[] $sensorTypes
     *
     * @return list<SensorType>
     */
    public static function sort(array $sensorTypes): array
    {
        $order = array_flip(array_map(static fn (self $case): string => $case->value, self::cases()));

        $sensorTypes = array_values($sensorTypes);
        usort($sensorTypes, static fn (self $a, self $b): int => $order[$a->value] <=> $order[$b->value]);

        return $sensorTypes;
    }
}
