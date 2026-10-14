<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\Route\ExplorerTile\ExplorerTileGrid;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ExplorerTileGridTest extends TestCase
{
    use MatchesSnapshots;

    public function testTilesForReferencePoints(): void
    {
        $this->assertSame(
            [
                ExplorerTileGrid::tileKey(0, ExplorerTileGrid::TILES_PER_AXIS - 1),
                ExplorerTileGrid::tileKey(8192, 8192),
                ExplorerTileGrid::tileKey(8361, 5481),
                ExplorerTileGrid::tileKey(15073, 9831),
                ExplorerTileGrid::tileKey(ExplorerTileGrid::TILES_PER_AXIS - 1, 0),
            ],
            new ExplorerTileGrid()->tilesFor(Json::decode((string) file_get_contents(__DIR__.'/fixtures/reference-points.json')))->toArray()
        );
    }

    public function testTilesForSkipsInvalidPoints(): void
    {
        $this->assertSame(
            [ExplorerTileGrid::tileKey(8361, 5481)],
            new ExplorerTileGrid()->tilesFor(Json::decode((string) file_get_contents(__DIR__.'/fixtures/stream-with-null-points.json')))->toArray()
        );

        $tiles = new ExplorerTileGrid()->tilesFor([]);
        $this->assertTrue($tiles->isEmpty());
        $this->assertSame(0, $tiles->count());
    }

    public function testTilesForARoute(): void
    {
        $tiles = new ExplorerTileGrid()->tilesFor(
            EncodedPolyline::fromString((string) file_get_contents(__DIR__.'/../fixtures/route-be-nl.txt'))->decodeAndPairLatLng()
        );

        $this->assertMatchesJsonSnapshot(Json::encode($tiles->toArray()));
    }
}
