<?php

namespace App\Tests\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesRepository;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Route\ExplorerTile\ActivityExplorerTilesBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityNewExplorerTilesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    public function testRender(): void
    {
        foreach ([
            ['earlier', '2024-01-01', [1, 2]],
            ['later', '2024-01-02', [1, 2, 3, 4]],
            ['latest', '2024-01-03', [3, 4, 5]],
            ['last', '2024-01-04', [1, 5]],
        ] as [$activityId, $startDateTime, $tiles]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
            $this->getContainer()->get(ActivityExplorerTilesRepository::class)->add(ActivityExplorerTilesBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withTiles(ExplorerTiles::fromArray($tiles))
                ->build());
        }

        $this->client->request('GET', '/api/internal/activities/activity-later/explorer-tiles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, activities.later, activity.route');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/internal/activities/activity-latest/explorer-tiles');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('1 new tile(s)', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/internal/activities/activity-last/explorer-tiles');

        $this->assertResponseIsSuccessful();
        $this->assertSame('', trim((string) $this->client->getResponse()->getContent()));
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->client->request('GET', '/api/internal/activities/activity-1/explorer-tiles');

        $this->assertResponseStatusCodeSame(404);
    }
}
