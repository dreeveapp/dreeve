<?php

namespace App\Tests\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityNeighboursRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    public function testRender(): void
    {
        foreach ([
            ['middle', '2024-01-02'],
            ['newest', '2024-01-03'],
        ] as [$activityId, $startDateTime]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withName('Activity '.$activityId)
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(), []
            ));
        }

        $this->client->request('GET', '/api/internal/activities/activity-middle/neighbours');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, activities');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/internal/activities/activity-903645/neighbours');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('activities/activity-middle', $content);
        $this->assertStringNotContainsString('data-keyboard-shortcut="ArrowLeft"', $content);
        $this->assertStringContainsString('aria-hidden="true"', $content);
    }

    public function testRenderForTheOnlyActivity(): void
    {
        $this->client->request('GET', '/api/internal/activities/activity-903645/neighbours');

        $this->assertResponseIsSuccessful();
        $this->assertSame('', trim((string) $this->client->getResponse()->getContent()));
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->client->request('GET', '/api/internal/activities/activity-1/neighbours');

        $this->assertResponseStatusCodeSame(404);
    }
}
