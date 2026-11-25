<?php

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Activity\Route\ExplorerTile\ExplorerTileScanner;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class ExplorerTileScannerTest extends ContainerTestCase
{
    public function testScan(): void
    {
        $streamData = [
            null,
            [51.048301, 3.724365],
            null,
            [51.0545],
            [51.048301, 3.790283],
            [51.033104, 3.812256],
        ];
        $polyline = (string) EncodedPolyline::fromCoordinates([[51.048301, 3.724365]]);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-stream'))
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline($polyline)
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('with-stream'))
            ->withStreamType(StreamType::LAT_LNG)
            ->withData($streamData)
            ->build());
        $this->getContainer()->get(ActivityScanRepository::class)->add(
            ActivityScan::create(activityId: ActivityId::fromUnprefixed('with-stream'), type: ActivityScanType::STREAMS)
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-polyline-only'))
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline($polyline)
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityScanRepository::class)->add(
            ActivityScan::create(activityId: ActivityId::fromUnprefixed('with-polyline-only'), type: ActivityScanType::STREAMS)
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('virtual'))
                ->withWorldType(WorldType::ZWIFT)
                ->withPolyline($polyline)
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('virtual'))
            ->withStreamType(StreamType::LAT_LNG)
            ->withData($streamData)
            ->build());
        $this->getContainer()->get(ActivityScanRepository::class)->add(
            ActivityScan::create(activityId: ActivityId::fromUnprefixed('virtual'), type: ActivityScanType::STREAMS)
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('streams-not-fetched'))
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline($polyline)
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('streams-not-fetched'))
            ->withStreamType(StreamType::LAT_LNG)
            ->withData($streamData)
            ->build());

        $scanner = new ExplorerTileScanner(
            $this->getContainer()->get(ActivityRepository::class),
            $this->getContainer()->get(ActivityStreamRepository::class),
            $this->getContainer()->get(ActivityScanRepository::class),
            $this->getContainer()->get(ActivityExplorerTilesRepository::class),
        );
        $this->assertTrue($scanner->scan(ActivityId::fromUnprefixed('with-stream')));
        $this->assertTrue($scanner->scan(ActivityId::fromUnprefixed('with-polyline-only')));
        $this->assertTrue($scanner->scan(ActivityId::fromUnprefixed('virtual')));
        $this->assertFalse($scanner->scan(ActivityId::fromUnprefixed('streams-not-fetched')));

        $this->assertSame(
            [
                'activity-with-polyline-only' => [ExplorerTileGrid::tileKey(8361, 5481)],
                'activity-with-stream' => [
                    ExplorerTileGrid::tileKey(8361, 5481),
                    ExplorerTileGrid::tileKey(8362, 5481),
                    ExplorerTileGrid::tileKey(8363, 5481),
                    ExplorerTileGrid::tileKey(8364, 5481),
                    ExplorerTileGrid::tileKey(8364, 5482),
                    ExplorerTileGrid::tileKey(8365, 5482),
                ],
            ],
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->findAll()
        );
        $this->assertSame(
            ['activity-virtual', 'activity-with-polyline-only', 'activity-with-stream'],
            $this->getConnection()->executeQuery(
                'SELECT activityId FROM ActivityScan WHERE type = "explorerTiles" ORDER BY activityId'
            )->fetchFirstColumn()
        );
    }
}
