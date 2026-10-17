<?php

namespace App\Tests\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesBuilder;
use App\Tests\ProvideTestData;

class HeatmapTilesRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $activityIds = $this->getContainer()->get(ActivityIdRepository::class)->findAll();
        $block = fn (int $fromX, int $toX): ExplorerTiles => ExplorerTiles::fromArray(array_merge(...array_map(
            fn (int $x): array => array_map(fn (int $y): int => ExplorerTileGrid::tileKey($x, $y), range(20, 22)),
            range($fromX, $toX),
        )));
        foreach (array_values($activityIds->toArray()) as $index => $activityId) {
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->add(ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withTiles(match ($index) {
                    0 => $block(10, 11),
                    1 => $block(11, 12),
                    default => ExplorerTiles::fromArray([1, 2, 3]),
                })
                ->build());
        }

        $this->client->request('GET', '/api/internal/heatmap/tiles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringEndsWith(
            'heatmap.tiles',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.route',
        );
        $this->assertSame(
            [
                'visited' => [
                    1, 2, 3,
                    ExplorerTileGrid::tileKey(10, 20), ExplorerTileGrid::tileKey(10, 21), ExplorerTileGrid::tileKey(10, 22),
                    ExplorerTileGrid::tileKey(11, 20), ExplorerTileGrid::tileKey(11, 22),
                    ExplorerTileGrid::tileKey(12, 20), ExplorerTileGrid::tileKey(12, 21), ExplorerTileGrid::tileKey(12, 22),
                ],
                'cluster' => [],
                'maxCluster' => [ExplorerTileGrid::tileKey(11, 21)],
                'maxSquare' => ['x' => 10, 'y' => 20, 'size' => 3],
                'stats' => ['visitedTiles' => 12, 'clusterTiles' => 1, 'maxCluster' => 1, 'maxSquare' => 3],
            ],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
