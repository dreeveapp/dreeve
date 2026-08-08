<?php

namespace App\Tests\Controller\Api\Activity;

use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivitySegmentsRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9542782314/segments');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testHandleForActivityWithoutAnySegments(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/activity-9756441709/segments');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty(trim((string) $this->client->getResponse()->getContent()));
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

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/activity/activity-1/segments');

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
}
