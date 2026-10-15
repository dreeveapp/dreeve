<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalActivityExplorerTilesRepositoryTest extends ContainerTestCase
{
    public function testAddAndFindAll(): void
    {
        foreach (['test' => '2024-01-02', 'test-2' => '2024-01-01'] as $activityId => $startDateTime) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
        }
        $repository = $this->getContainer()->get(ActivityExplorerTilesRepository::class);
        $repository->add(
            ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withTiles(ExplorerTiles::fromArray([40]))
                ->build()
        );
        $repository->add(
            ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withTiles(ExplorerTiles::fromArray([10, 20, 30]))
                ->build()
        );

        $this->assertSame(
            [
                'activity-test' => [10, 20, 30],
                'activity-test-2' => [40],
            ],
            $repository->findAll()
        );
        $this->assertEquals(
            [
                [
                    'activityId' => ActivityId::fromUnprefixed('test-2'),
                    'startDateTime' => SerializableDateTime::fromString('2024-01-01'),
                    'tiles' => ExplorerTiles::fromArray([40]),
                ],
                [
                    'activityId' => ActivityId::fromUnprefixed('test'),
                    'startDateTime' => SerializableDateTime::fromString('2024-01-02'),
                    'tiles' => ExplorerTiles::fromArray([10, 20, 30]),
                ],
            ],
            $repository->findAllOrderedByStartDateTime()
        );
    }

    public function testDeleteForActivity(): void
    {
        $repository = $this->getContainer()->get(ActivityExplorerTilesRepository::class);
        $repository->add(
            ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->build()
        );
        $repository->add(
            ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->build()
        );

        $repository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertSame(
            ['activity-test-2'],
            $this->getConnection()->executeQuery('SELECT activityId FROM ActivityExplorerTiles')->fetchFirstColumn()
        );
    }
}
