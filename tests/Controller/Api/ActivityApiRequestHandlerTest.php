<?php

namespace App\Tests\Controller\Api;

use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityApiRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testSegments(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/segments');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testSegmentsForActivityWithoutAny(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441709/segments');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty(trim((string) $this->client->getResponse()->getContent()));
    }

    public function testBestEfforts(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/best-efforts');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGpx(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441741/route.gpx');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGpxForActivityWithoutTimeStream(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-9542782314" has no GPX data'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testGpxWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testItAcceptsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/segments');
        $prefixed = (string) $this->client->getResponse()->getContent();

        $this->client->request('GET', '/api/activity/9542782314/segments');

        $this->assertResponseIsSuccessful();
        $this->assertEquals($prefixed, $this->client->getResponse()->getContent());
    }

    public function testSegmentsWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/segments');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testBestEffortsWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/best-efforts');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Activity "activity-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testItOutranksTheCatchAllApiRoute(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/segments');

        $this->assertEquals(
            'api_activity_segments',
            $this->client->getRequest()->attributes->get('_route')
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
