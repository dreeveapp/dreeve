<?php

namespace App\Tests\Domain\Geography\ExplorerTile;

use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Domain\Geography\ExplorerTile\ExplorerTileSquare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExplorerTilesTest extends TestCase
{
    /**
     * @param list<string>          $grid
     * @param list<array{int, int}> $expectedMaxCluster
     */
    #[DataProvider(methodName: 'provideGrids')]
    public function testClustersAndMaxSquare(
        array $grid,
        int $offsetX,
        int $offsetY,
        int $expectedClusterTileCount,
        array $expectedMaxCluster,
        ?ExplorerTileSquare $expectedMaxSquare,
    ): void {
        $tiles = self::tilesFromGrid($grid, $offsetX, $offsetY);
        $clusters = $tiles->clusters();

        $this->assertSame($expectedClusterTileCount, $clusters->getClusterTiles()->count());
        $this->assertSame(
            array_map(fn (array $tile): int => ExplorerTileGrid::tileKey($tile[0] + $offsetX, $tile[1] + $offsetY), $expectedMaxCluster),
            $clusters->getMaxCluster()->toArray(),
        );
        $this->assertEquals($expectedMaxSquare, $tiles->maxSquare());
    }

    public static function provideGrids(): iterable
    {
        yield 'separate clusters' => [
            [
                '######....',
                '######..##',
                '######..##',
                '######..##',
                '######..##',
                '######....',
                '..........',
                '.####.....',
                '.####.....',
                '.####.....',
                '.####.....',
            ],
            100, 200, 20,
            [
                [1, 1], [1, 2], [1, 3], [1, 4],
                [2, 1], [2, 2], [2, 3], [2, 4],
                [3, 1], [3, 2], [3, 3], [3, 4],
                [4, 1], [4, 2], [4, 3], [4, 4],
            ],
            new ExplorerTileSquare(x: 100, y: 200, size: 6),
        ];
        yield 'hole in the middle' => [
            [
                '#####',
                '#####',
                '##.##',
                '#####',
                '#####',
            ],
            100, 200, 4,
            [[1, 1]],
            new ExplorerTileSquare(x: 100, y: 200, size: 2),
        ];
        yield 'top left edge of the world' => [
            [
                '###',
                '###',
                '###',
            ],
            0, 0, 1,
            [[1, 1]],
            new ExplorerTileSquare(x: 0, y: 0, size: 3),
        ];
        yield 'bottom right edge of the world' => [
            [
                '###',
                '###',
                '###',
            ],
            ExplorerTileGrid::TILES_PER_AXIS - 3, ExplorerTileGrid::TILES_PER_AXIS - 3, 1,
            [[1, 1]],
            new ExplorerTileSquare(x: ExplorerTileGrid::TILES_PER_AXIS - 3, y: ExplorerTileGrid::TILES_PER_AXIS - 3, size: 3),
        ];
    }

    public function testClustersAndMaxSquareWithoutTiles(): void
    {
        $tiles = ExplorerTiles::fromArray([]);

        $this->assertTrue($tiles->clusters()->getClusterTiles()->isEmpty());
        $this->assertTrue($tiles->clusters()->getMaxCluster()->isEmpty());
        $this->assertNull($tiles->maxSquare());
    }

    public function testUnionAndWithout(): void
    {
        $tiles = ExplorerTiles::union([[5, 1, 3], [3, 4], []]);

        $this->assertSame([1, 3, 4, 5], $tiles->toArray());
        $this->assertSame([1, 5], $tiles->without(ExplorerTiles::fromArray([3, 4, 9]))->toArray());
    }

    /**
     * @param list<string> $grid
     */
    private static function tilesFromGrid(array $grid, int $offsetX, int $offsetY): ExplorerTiles
    {
        $tiles = [];
        foreach ($grid as $y => $row) {
            foreach (str_split($row) as $x => $cell) {
                if ('#' === $cell) {
                    $tiles[ExplorerTileGrid::tileKey($x + $offsetX, $y + $offsetY)] = true;
                }
            }
        }

        return ExplorerTiles::fromFlippedTileKeys($tiles);
    }
}
