<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

final readonly class ConnectedSensor implements \JsonSerializable
{
    /**
     * @param list<SensorType> $sensorTypes
     */
    private function __construct(
        private int $manufacturer,
        private ?int $product,
        private ?int $serialNumber,
        private ?string $name,
        private array $sensorTypes,
    ) {
    }

    public static function create(
        int $manufacturer,
        ?int $product,
        ?int $serialNumber,
        ?string $name,
        SensorType ...$sensorTypes,
    ): self {
        return new self(
            manufacturer: $manufacturer,
            product: $product,
            serialNumber: $serialNumber,
            name: $name,
            sensorTypes: SensorType::sort(array_values(array_unique($sensorTypes, SORT_REGULAR))),
        );
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function fromArray(array $value): ?self
    {
        if (!is_numeric($value['manufacturer'] ?? null)) {
            return null;
        }

        $sensorTypes = array_filter(array_map(
            static fn (mixed $sensorType): ?SensorType => is_string($sensorType) ? SensorType::tryFrom($sensorType) : null,
            is_array($value['sensorTypes'] ?? null) ? $value['sensorTypes'] : [],
        ));

        if ([] === $sensorTypes) {
            return null;
        }

        return self::create(
            (int) $value['manufacturer'],
            is_numeric($value['product'] ?? null) ? (int) $value['product'] : null,
            is_numeric($value['serialNumber'] ?? null) ? (int) $value['serialNumber'] : null,
            is_string($value['name'] ?? null) ? $value['name'] : null,
            ...$sensorTypes,
        );
    }

    public function getId(): SensorId
    {
        return SensorId::fromManufacturerAndSerialNumber(
            manufacturer: $this->manufacturer,
            serialNumber: $this->serialNumber,
            product: $this->product,
        );
    }

    public function getManufacturer(): int
    {
        return $this->manufacturer;
    }

    public function getProduct(): ?int
    {
        return $this->product;
    }

    public function getSerialNumber(): ?int
    {
        return $this->serialNumber;
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

    public function hasSensorType(SensorType $sensorType): bool
    {
        return in_array($sensorType, $this->sensorTypes, true);
    }

    public function mergeWith(self $other): self
    {
        return self::create(
            $this->manufacturer,
            $this->product ?? $other->product,
            $this->serialNumber ?? $other->serialNumber,
            $this->name ?? $other->name,
            ...[...$this->sensorTypes, ...$other->sensorTypes],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'manufacturer' => $this->manufacturer,
            'product' => $this->product,
            'serialNumber' => $this->serialNumber,
            'name' => $this->name,
            'sensorTypes' => array_map(static fn (SensorType $sensorType): string => $sensorType->value, $this->sensorTypes),
        ];
    }
}
