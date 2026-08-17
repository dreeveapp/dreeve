<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

/**
 * @implements \IteratorAggregate<int, ConnectedSensor>
 */
final readonly class ConnectedSensors implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @param list<ConnectedSensor> $sensors
     */
    private function __construct(
        private array $sensors,
    ) {
    }

    public static function fromSensors(ConnectedSensor ...$sensors): self
    {
        $merged = [];
        foreach ($sensors as $sensor) {
            $id = (string) $sensor->getId();
            $merged[$id] = isset($merged[$id]) ? $merged[$id]->mergeWith($sensor) : $sensor;
        }

        ksort($merged);

        return new self(array_values($merged));
    }

    /**
     * @param array<int, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return self::fromSensors(...array_filter(array_map(
            static fn (mixed $value): ?ConnectedSensor => is_array($value) ? ConnectedSensor::fromArray($value) : null,
            $values,
        )));
    }

    public function hasAnyOf(SensorType ...$sensorTypes): bool
    {
        foreach ($this->sensors as $sensor) {
            foreach ($sensorTypes as $sensorType) {
                if ($sensor->hasSensorType($sensorType)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->sensors);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return array_map(static fn (ConnectedSensor $sensor): array => $sensor->jsonSerialize(), $this->sensors);
    }
}
