<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTileHistory;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTileHistoryEntry;
use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class ExplorerTileHistoryTest extends ContainerTestCase
{
    public function testCalculate(): void
    {
        $square = fn (int $size): ExplorerTiles => ExplorerTiles::fromArray(array_merge(...array_map(
            fn (int $x): array => array_map(fn (int $y): int => ExplorerTileGrid::tileKey($x, $y), range(20, 19 + $size)),
            range(10, 9 + $size),
        )));
        foreach ([
            ['3x3', '2024-01-03', $square(3)],
            ['2x2', '2024-01-01', $square(2)],
            ['2x2-again', '2024-01-02', $square(2)],
        ] as [$activityId, $startDateTime, $tiles]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->add(ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withTiles($tiles)
                ->build());
        }

        $this->assertEquals(
            [
                new ExplorerTileHistoryEntry(
                    activityId: ActivityId::fromUnprefixed('2x2'),
                    startDateTime: SerializableDateTime::fromString('2024-01-01'),
                    maxSquareSize: 2,
                    maxClusterSize: 0,
                ),
                new ExplorerTileHistoryEntry(
                    activityId: ActivityId::fromUnprefixed('2x2-again'),
                    startDateTime: SerializableDateTime::fromString('2024-01-02'),
                    maxSquareSize: 2,
                    maxClusterSize: 0,
                ),
                new ExplorerTileHistoryEntry(
                    activityId: ActivityId::fromUnprefixed('3x3'),
                    startDateTime: SerializableDateTime::fromString('2024-01-03'),
                    maxSquareSize: 3,
                    maxClusterSize: 1,
                ),
            ],
            $this->getContainer()->get(ExplorerTileHistory::class)->calculate()
        );
    }

    public function testCalculateWithoutTiles(): void
    {
        $this->assertSame([], $this->getContainer()->get(ExplorerTileHistory::class)->calculate());
    }
}
