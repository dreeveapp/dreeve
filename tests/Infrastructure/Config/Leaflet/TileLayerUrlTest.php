<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config\Leaflet;

use App\Infrastructure\Config\Leaflet\TileLayerUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TileLayerUrlTest extends TestCase
{
    #[DataProvider('provideValidUrls')]
    public function testItAcceptsValidTileLayerUrls(string $url): void
    {
        $this->assertSame($url, (string) TileLayerUrl::fromString($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidUrls(): iterable
    {
        yield 'xyz order' => ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'];
        yield 'zyx order' => ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png'];
        yield 'subdomain placeholder' => ['https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png'];
    }

    #[DataProvider('provideUrlsWithMissingPlaceholders')]
    public function testItThrowsWhenPlaceholdersAreMissing(string $url, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException($expectedExceptionMessage));

        TileLayerUrl::fromString($url);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUrlsWithMissingPlaceholders(): iterable
    {
        yield 'no placeholders at all' => [
            'https://tile.openstreetmap.org/tiles.png',
            'Invalid tile layer url "https://tile.openstreetmap.org/tiles.png", it must contain the placeholders {z}, {x} and {y}',
        ];
        yield 'missing y' => [
            'https://tile.openstreetmap.org/{z}/{x}.png',
            'Invalid tile layer url "https://tile.openstreetmap.org/{z}/{x}.png", it must contain the placeholders {z}, {x} and {y}',
        ];
    }

    public function testItThrowsForAMalformedUrl(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid url "not a url {z}/{x}/{y}"'));

        TileLayerUrl::fromString('not a url {z}/{x}/{y}');
    }
}
