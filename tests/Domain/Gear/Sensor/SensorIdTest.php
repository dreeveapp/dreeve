<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Sensor;

use App\Domain\Gear\Sensor\SensorId;
use PHPUnit\Framework\TestCase;

class SensorIdTest extends TestCase
{
    public function testItIsPrefixed(): void
    {
        $this->assertSame(
            'sensor-1-3485828557',
            (string) SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: 3485828557, product: 3578)
        );
    }

    public function testTheSameHardwareYieldsTheSameIdRegardlessOfProduct(): void
    {
        $this->assertEquals(
            SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: 3485049140, product: 3592),
            SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: 3485049140, product: null)
        );
    }

    public function testItDistinguishesTheSameSerialFromAnotherManufacturer(): void
    {
        $this->assertNotEquals(
            SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: 42, product: null),
            SensorId::fromManufacturerAndSerialNumber(manufacturer: 123, serialNumber: 42, product: null)
        );
    }

    public function testItFallsBackToTheProductWhenThereIsNoSerialNumber(): void
    {
        $this->assertSame(
            'sensor-1-p3592',
            (string) SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: null, product: 3592)
        );
    }

    public function testItFallsBackToTheManufacturerAloneWhenNothingElseIsKnown(): void
    {
        $this->assertSame(
            'sensor-1-p0',
            (string) SensorId::fromManufacturerAndSerialNumber(manufacturer: 1, serialNumber: null, product: null)
        );
    }
}
