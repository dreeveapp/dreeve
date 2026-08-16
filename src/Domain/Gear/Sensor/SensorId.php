<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class SensorId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'sensor-';
    }

    public static function fromManufacturerAndSerialNumber(int $manufacturer, ?int $serialNumber, ?int $product): self
    {
        if (null !== $serialNumber) {
            return self::fromUnprefixed(sprintf('%d-%d', $manufacturer, $serialNumber));
        }

        return self::fromUnprefixed(sprintf('%d-p%d', $manufacturer, $product ?? 0));
    }
}
