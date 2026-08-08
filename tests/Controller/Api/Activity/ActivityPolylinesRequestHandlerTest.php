<?php

namespace App\Tests\Controller\Api\Activity;

use App\Controller\Api\Activity\ActivityPolylinesRequestHandler;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityPolylinesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        // This activity has a map but no combined stream, so it falls back to the encoded polyline.
        $this->client->request('GET', '/api/activity/activity-9830227112/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();
        $handler = static::getContainer()->get(ActivityPolylinesRequestHandler::class);

        $firstResponse = $handler->handle('activity-9830227112');
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Cache'));
        $this->assertStringEndsWith(
            'activity.9830227112.polylines',
            (string) $firstResponse->headers->get('X-Cache-Key')
        );
        $this->assertEquals(
            'settings.appearance, settings.general, activities.9830227112',
            $firstResponse->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $handler->handle('9830227112');

        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
    }

    public function testHandleForActivityWithoutAMap(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441741/polylines');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-9756441741" has no map'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/polylines');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }
}
