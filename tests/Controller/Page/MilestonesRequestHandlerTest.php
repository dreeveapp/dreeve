<?php

namespace App\Tests\Controller\Page;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTileGrid;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesBuilder;
use App\Tests\ProvideTestData;
use Doctrine\DBAL\Connection;
use Spatie\Snapshots\MatchesSnapshots;

class MilestonesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $square = fn (int $size): ExplorerTiles => ExplorerTiles::fromArray(array_merge(...array_map(
            fn (int $x): array => array_map(fn (int $y): int => ExplorerTileGrid::tileKey($x, $y), range(20, 19 + $size)),
            range(10, 9 + $size),
        )));
        $activityIds = $this->getContainer()->get(Connection::class)->executeQuery('SELECT activityId FROM Activity ORDER BY startDateTime LIMIT 2')->fetchFirstColumn();
        foreach ([3, 4] as $index => $size) {
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->add(ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromString($activityIds[$index]))
                ->withTiles($square($size))
                ->build());
        }

        $this->client->request('GET', '/milestones');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'milestones',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activities, gear, settings.metrics, settings.activityExclusions, activity.route');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
