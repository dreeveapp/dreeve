<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<Sensor>
 */
class Sensors extends Collection
{
    public function getItemClassName(): string
    {
        return Sensor::class;
    }

    /**
     * @return list<SensorType>
     */
    public function getSensorTypes(): array
    {
        $sensorTypes = [];
        foreach ($this as $sensor) {
            foreach ($sensor->getSensorTypes() as $sensorType) {
                $sensorTypes[$sensorType->value] = $sensorType;
            }
        }

        return SensorType::sort(array_values($sensorTypes));
    }
}
