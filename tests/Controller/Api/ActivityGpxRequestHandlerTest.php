<?php

namespace App\Tests\Controller\Api;

use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityGpxRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441741/route.gpx');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testHandleForActivityWithoutTimeStream(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-9542782314" has no GPX data'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testItServesGpxFromTheEndpointAndNotFromTheBuildDirectory(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441741/route.gpx');

        $this->assertEquals(
            'api_activity_gpx',
            $this->client->getRequest()->attributes->get('_route')
        );
    }
}
