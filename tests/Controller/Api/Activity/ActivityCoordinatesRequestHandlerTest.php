<?php

namespace App\Tests\Controller\Api\Activity;

use App\Controller\Api\Activity\ActivityCoordinatesRequestHandler;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityCoordinatesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441741/coordinates');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();

        // The array cache adapter is reset between HTTP requests, so drive the handler directly
        // to observe a second, cached call.
        $handler = static::getContainer()->get(ActivityCoordinatesRequestHandler::class);

        $firstResponse = $handler->handle('activity-9756441741');
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Cache'));
        $this->assertStringEndsWith(
            'activity.9756441741.coordinates',
            (string) $firstResponse->headers->get('X-Cache-Key')
        );
        $this->assertEquals(
            'settings.appearance, settings.general, activities.9756441741',
            $firstResponse->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $handler->handle('9756441741');

        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
    }

    public function testHandleForActivityWithoutCombinedStream(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9830227112/coordinates');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-9830227112" has no combined stream'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/coordinates');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }
}
