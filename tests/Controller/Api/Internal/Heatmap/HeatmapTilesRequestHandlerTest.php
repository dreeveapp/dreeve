<?php

namespace App\Tests\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
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
        foreach ($activityIds as $activityId) {
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->add(ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId($activityId)
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
            'settings.appearance, settings.general, activity.route',
        );
        $tiles = Json::decode((string) $this->client->getResponse()->getContent());
        $expectedActivityIds = array_map(strval(...), $activityIds->toArray());
        sort($expectedActivityIds);
        $this->assertSame($expectedActivityIds, array_keys($tiles));
        $this->assertSame([1, 2, 3], $tiles[$expectedActivityIds[0]]);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
