<?php

namespace App\Tests\Controller\Api\Internal;

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

        $this->client->request('GET', '/api/internal/activity/activity-9756441741/route.gpx');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testHandleConvertsLocalStartDateToUtc(): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Brussels');

        try {
            $this->provideFullTestSet();

            $this->client->request('GET', '/api/internal/activity/activity-9756441741/route.gpx');

            $this->assertResponseIsSuccessful();
            $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function testHandleForActivityWithoutTimeStream(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/activity/activity-9542782314/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', '404');
    }

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/api/internal/activity/activity-1/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', '404');
    }

    public function testItServesGpxFromTheEndpointAndNotFromTheBuildDirectory(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/activity/activity-9756441741/route.gpx');

        $this->assertEquals(
            'api_activity_gpx',
            $this->client->getRequest()->attributes->get('_route')
        );
    }
}
