<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTiles;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalActivityExplorerTilesRepositoryTest extends ContainerTestCase
{
    public function testAddAndFindAllOnHeatmap(): void
    {
        $repository = $this->getContainer()->get(ActivityExplorerTilesRepository::class);

        foreach ([
            'on-heatmap' => [SportType::RIDE, WorldType::REAL_WORLD, 'tqafAua~y^vG{D', ['country_code' => 'BE'], [10, 20, 30]],
            'on-heatmap-2' => [SportType::RUN, WorldType::REAL_WORLD, 'tqafAua~y^vG{D', ['country_code' => 'BE'], [40]],
            'without-polyline' => [SportType::RIDE, WorldType::REAL_WORLD, null, ['country_code' => 'BE'], [50]],
            'without-country' => [SportType::RIDE, WorldType::REAL_WORLD, 'tqafAua~y^vG{D', [], [60]],
            'virtual-world' => [SportType::RIDE, WorldType::ZWIFT, 'tqafAua~y^vG{D', ['country_code' => 'BE'], [70]],
            'virtual-sport-type' => [SportType::VIRTUAL_RIDE, WorldType::REAL_WORLD, 'tqafAua~y^vG{D', ['country_code' => 'BE'], [80]],
        ] as $activityId => [$sportType, $worldType, $polyline, $routeGeography, $tiles]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withSportType($sportType)
                    ->withWorldType($worldType)
                    ->withPolyline($polyline)
                    ->withRouteGeography(RouteGeography::create($routeGeography))
                    ->build(),
                []
            ));
            $repository->add(
                ActivityExplorerTilesBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withTiles(ExplorerTiles::fromArray($tiles))
                    ->build()
            );
        }

        $this->assertEquals(
            [
                ['activityId' => 'activity-on-heatmap', 'tileCount' => 3],
                ['activityId' => 'activity-on-heatmap-2', 'tileCount' => 1],
                ['activityId' => 'activity-virtual-sport-type', 'tileCount' => 1],
                ['activityId' => 'activity-virtual-world', 'tileCount' => 1],
                ['activityId' => 'activity-without-country', 'tileCount' => 1],
                ['activityId' => 'activity-without-polyline', 'tileCount' => 1],
            ],
            $this->getConnection()
                ->executeQuery('SELECT activityId, tileCount FROM ActivityExplorerTiles ORDER BY activityId')
                ->fetchAllAssociative()
        );
        $this->assertSame(
            [
                'activity-on-heatmap' => [10, 20, 30],
                'activity-on-heatmap-2' => [40],
            ],
            $repository->findAllOnHeatmap()
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
