<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;

final readonly class DbalSensorRepository extends DbalRepository implements SensorRepository
{
    public function findAll(): Sensors
    {
        $results = $this->connection->executeQuery(
            "SELECT connectedSensors, COUNT(*) AS activityCount
             FROM Activity
             WHERE connectedSensors IS NOT NULL AND connectedSensors != '[]'
             GROUP BY connectedSensors"
        )->fetchAllAssociative();

        /** @var array<string, Sensor> $sensors */
        $sensors = [];
        foreach ($results as $result) {
            $activityCount = (int) $result['activityCount'];

            foreach (ConnectedSensors::fromArray(Json::decode($result['connectedSensors'])) as $connectedSensor) {
                $sensorId = (string) $connectedSensor->getId();
                $known = $sensors[$sensorId] ?? null;

                $sensors[$sensorId] = Sensor::fromState(
                    name: $connectedSensor->getName() ?? $known?->getName(),
                    sensorTypes: SensorType::sort(array_values(array_unique(
                        [...$connectedSensor->getSensorTypes(), ...$known?->getSensorTypes() ?? []],
                        SORT_REGULAR
                    ))),
                    activityCount: $activityCount + ($known?->getActivityCount() ?? 0),
                );
            }
        }

        uasort($sensors, static fn (Sensor $a, Sensor $b): int => $b->getActivityCount() <=> $a->getActivityCount());

        return Sensors::fromArray(array_values($sensors));
    }
}
