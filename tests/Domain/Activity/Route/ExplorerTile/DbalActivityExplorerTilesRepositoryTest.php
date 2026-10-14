<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTiles;
use App\Tests\ContainerTestCase;

class DbalActivityExplorerTilesRepositoryTest extends ContainerTestCase
{
    public function testAddAndFindAll(): void
    {
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

        $this->assertEquals(
            [
                ['activityId' => 'activity-test', 'tileCount' => 3],
                ['activityId' => 'activity-test-2', 'tileCount' => 1],
            ],
            $this->getConnection()
                ->executeQuery('SELECT activityId, tileCount FROM ActivityExplorerTiles ORDER BY activityId')
                ->fetchAllAssociative()
        );
        $this->assertSame(
            [
                'activity-test' => [10, 20, 30],
                'activity-test-2' => [40],
            ],
            $repository->findAll()
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

        $this->assertSame(['activity-test-2'], array_keys($repository->findAll()));
    }
}
