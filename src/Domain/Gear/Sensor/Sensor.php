<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

final readonly class Sensor
{
    /**
     * @param list<SensorType> $sensorTypes
     */
    private function __construct(
        private SensorId $sensorId,
        private ?string $name,
        private array $sensorTypes,
        private int $activityCount,
    ) {
    }

    /**
     * @param list<SensorType> $sensorTypes
     */
    public static function fromState(
        SensorId $sensorId,
        ?string $name,
        array $sensorTypes,
        int $activityCount,
    ): self {
        return new self(
            sensorId: $sensorId,
            name: $name,
            sensorTypes: $sensorTypes,
            activityCount: $activityCount,
        );
    }

    public function getId(): SensorId
    {
        return $this->sensorId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return list<SensorType>
     */
    public function getSensorTypes(): array
    {
        return $this->sensorTypes;
    }

    public function getActivityCount(): int
    {
        return $this->activityCount;
    }
}
