<?php

namespace App\Tests\Domain\Zwift;

use App\Domain\Zwift\CouldNotDetermineZwiftMap;
use App\Domain\Zwift\ZwiftMap;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZwiftMapTest extends TestCase
{
    #[DataProvider(methodName: 'provideStartingCoordinates')]
    public function testFromStartingCoordinate(string $latitude, string $longitude, string $expectedLabel, string $expectedOverlayImageUrl, string $expectedBackgroundColor): void
    {
        $map = ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
            Latitude::fromString($latitude),
            Longitude::fromString($longitude),
        ));

        $this->assertSame($expectedLabel, $map->getLabel());
        $this->assertSame($expectedOverlayImageUrl, $map->getOverlayImageUrl());
        $this->assertSame($expectedBackgroundColor, $map->getBackgroundColor());
    }

    public function testFromStartingCoordinateItShouldThrow(): void
    {
        $this->expectExceptionObject(new CouldNotDetermineZwiftMap('Could not determine Zwift map [1,1]'));

        ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
            Latitude::fromString('1'), Longitude::fromString('1')
        ));
    }

    public function testGetTileLayer(): void
    {
        $this->assertNull(ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
            Latitude::fromString('44.5308037'), Longitude::fromString('11.26261748')
        ))->getTileLayer());
    }

    public function testGetMinAndMaxZoom(): void
    {
        $this->assertEquals(
            18,
            ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
                Latitude::fromString('44.5308037'), Longitude::fromString('11.26261748')
            ))->getMaxZoom()
        );
        $this->assertEquals(
            12,
            ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
                Latitude::fromString('44.5308037'), Longitude::fromString('11.26261748')
            ))->getMinZoom()
        );
    }

    public function testGetOverlayImageUrl(): void
    {
        $this->assertEquals(
            '/assets/images/maps/zwift-bologna.webp',
            ZwiftMap::forStartingCoordinate(Coordinate::createFromLatAndLng(
                Latitude::fromString('44.5308037'), Longitude::fromString('11.26261748')
            ))->getOverlayImageUrl()
        );
    }

    public static function provideStartingCoordinates(): iterable
    {
        yield 'Bologna' => ['44.5308037', '11.26261748', 'Bologna', '/assets/images/maps/zwift-bologna.webp', '#bbbbb7'];
        yield 'Crit City' => ['-10.3657', '165.7824', 'Crit City', '/assets/images/maps/zwift-crit-city.webp', '#bbbbb7'];
        yield 'France' => ['-21.7564', '166.26125', 'France', '/assets/images/maps/zwift-france.webp', '#bbbbb7'];
        yield 'Innsbruck' => ['47.2947', '11.3501', 'Innsbruck', '/assets/images/maps/zwift-innsbruck.webp', '#bbbbb7'];
        yield 'London' => ['51.5362', '-0.1776', 'London', '/assets/images/maps/zwift-london.webp', '#bbbbb7'];
        yield 'Makuri Islands' => ['-10.7375', '165.7828', 'Makuri Islands', '/assets/images/maps/zwift-makuri-islands.webp', '#bbbbb7'];
        yield 'New York' => ['40.81725', '-74.0227', 'New York', '/assets/images/maps/zwift-new-york.webp', '#bbbbb7'];
        yield 'Paris' => ['48.9058', '2.2561', 'Paris', '/assets/images/maps/zwift-paris.webp', '#bbbbb7'];
        yield 'Richmond' => ['37.5774', '-77.48954', 'Richmond', '/assets/images/maps/zwift-richmond.webp', '#bbbbb7'];
        yield 'Scotland' => ['55.675959999999996', '-5.28053', 'Scotland', '/assets/images/maps/zwift-scotland.webp', '#bbbbb7'];
        yield 'Watopia' => ['-11.626', '166.87747', 'Watopia', '/assets/images/maps/zwift-watopia.webp', '#bbbbb7'];
        yield 'Yorkshire' => ['54.0254', '-1.6320', 'Yorkshire', '/assets/images/maps/zwift-yorkshire.webp', '#bbbbb7'];
    }
}
