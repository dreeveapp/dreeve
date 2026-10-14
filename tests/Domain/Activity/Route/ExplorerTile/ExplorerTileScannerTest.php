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
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class ExplorerTileScannerTest extends ContainerTestCase
{
    public function testScan(): void
    {
        $streamData = Json::decode((string) file_get_contents(__DIR__.'/../../../Geography/ExplorerTile/fixtures/stream-with-gaps.json'));
        $polyline = (string) EncodedPolyline::fromCoordinates([[51.048301, 3.724365]]);

        foreach ([
            'with-stream' => [WorldType::REAL_WORLD, $streamData, true],
            'with-polyline-only' => [WorldType::REAL_WORLD, null, true],
            'virtual' => [WorldType::ZWIFT, $streamData, true],
            'streams-not-fetched' => [WorldType::REAL_WORLD, $streamData, false],
        ] as $activityId => [$worldType, $latLng, $streamsFetched]) {
            $activityId = ActivityId::fromUnprefixed($activityId);
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withWorldType($worldType)
                    ->withPolyline($polyline)
                    ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                    ->build(),
                []
            ));
            if (null !== $latLng) {
                $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withStreamType(StreamType::LAT_LNG)
                    ->withData($latLng)
                    ->build());
            }
            if ($streamsFetched) {
                $this->getContainer()->get(ActivityScanRepository::class)->add(
                    ActivityScan::create(activityId: $activityId, type: ActivityScanType::STREAMS)
                );
            }
        }

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
