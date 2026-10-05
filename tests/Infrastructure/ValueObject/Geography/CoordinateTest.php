<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CoordinateTest extends TestCase
{
    public function testCreateFromOptionalLatAndLng(): void
    {
        $coordinate = Coordinate::createFromOptionalLatAndLng(
            latitude: Latitude::fromString('3'),
            longitude: Longitude::fromString('2'),
        );

        $this->assertEquals(
            Latitude::fromString('3'),
            $coordinate->getLatitude()
        );
        $this->assertEquals(
            Longitude::fromString('2'),
            $coordinate->getLongitude()
        );
        $this->assertNull(Coordinate::createFromOptionalLatAndLng(
            latitude: Latitude::fromString('3'),
            longitude: null
        ));
        $this->assertNull(Coordinate::createFromOptionalLatAndLng(
            latitude: null,
            longitude: Longitude::fromString('2')
        ));
    }

    public function testJsonSerialize(): void
    {
        $coordinate = Coordinate::createFromOptionalLatAndLng(
            latitude: Latitude::fromString('3'),
            longitude: Longitude::fromString('2'),
        );

        $this->assertSame('[3,2]', Json::encode($coordinate));
    }

    #[TestWith([Latitude::class, '91', 'Invalid latitude value: 91'])]
    #[TestWith([Longitude::class, '181', 'Invalid longitude value: 181'])]
    public function testItShouldThrowWhenOutOfRange(string $class, string $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException($expectedMessage));

        $class::fromString($value);
    }
}
