<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Domain\Milestone\Context\MaxClusterContext;
use App\Domain\Milestone\Context\MaxSquareContext;
use App\Domain\Milestone\Discoverer\ExplorerTilesMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\SimpleUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesBuilder;

class ExplorerTilesMilestoneDiscovererTest extends ContainerTestCase
{
    public function testDiscover(): void
    {
        $square = fn (int $size): ExplorerTiles => ExplorerTiles::fromArray(array_merge(...array_map(
            fn (int $x): array => array_map(fn (int $y): int => ExplorerTileGrid::tileKey($x, $y), range(20, 19 + $size)),
            range(10, 9 + $size),
        )));
        foreach ([
            ['7x7', '2024-01-05', $square(7)],
            ['6x6', '2024-01-04', $square(6)],
            ['2x2', '2024-01-01', $square(2)],
            ['3x3', '2024-01-02', $square(3)],
            ['3x3-again', '2024-01-03', $square(3)],
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
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02'),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_SQUARE,
                    context: new MaxSquareContext(size: 3),
                )->withActivityId(ActivityId::fromUnprefixed('3x3')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-04'),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_SQUARE,
                    context: new MaxSquareContext(size: 6),
                )
                    ->withActivityId(ActivityId::fromUnprefixed('6x6'))
                    ->withPrevious(PreviousMilestone::create(
                        previousMilestoneId: MilestoneId::fromString('milestone-1'),
                        threshold: SimpleUnit::from(3),
                        achievedOn: SerializableDateTime::fromString('2024-01-02'),
                    )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-04'),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_CLUSTER,
                    context: new MaxClusterContext(tileCount: 10),
                )->withActivityId(ActivityId::fromUnprefixed('6x6')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-05'),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_SQUARE,
                    context: new MaxSquareContext(size: 7),
                )
                    ->withActivityId(ActivityId::fromUnprefixed('7x7'))
                    ->withPrevious(PreviousMilestone::create(
                        previousMilestoneId: MilestoneId::fromString('milestone-2'),
                        threshold: SimpleUnit::from(6),
                        achievedOn: SerializableDateTime::fromString('2024-01-04'),
                    )),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-5'),
                    achievedOn: SerializableDateTime::fromString('2024-01-05'),
                    category: MilestoneCategory::EXPLORER_TILES_MAX_CLUSTER,
                    context: new MaxClusterContext(tileCount: 25),
                )
                    ->withActivityId(ActivityId::fromUnprefixed('7x7'))
                    ->withPrevious(PreviousMilestone::create(
                        previousMilestoneId: MilestoneId::fromString('milestone-3'),
                        threshold: SimpleUnit::from(10),
                        achievedOn: SerializableDateTime::fromString('2024-01-04'),
                    )),
            ],

            $this->getContainer()->get(ExplorerTilesMilestoneDiscoverer::class)->discover(new MilestoneIdFactory())->toArray(),
        );
    }

    public function testDiscoverWithoutTiles(): void
    {
        $this->assertTrue($this->getContainer()->get(ExplorerTilesMilestoneDiscoverer::class)->discover(new MilestoneIdFactory())->isEmpty());
    }
}
