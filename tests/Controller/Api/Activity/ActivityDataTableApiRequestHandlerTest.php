<?php

namespace App\Tests\Controller\Api\Activity;

use App\Controller\Api\Activity\ActivityDataTableApiRequestHandler;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityDataTableApiRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/activity/data-table.json');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();

        $handler = static::getContainer()->get(ActivityDataTableApiRequestHandler::class);

        $firstResponse = $handler->handle();
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Cache'));
        $this->assertStringEndsWith('activity.data-table', (string) $firstResponse->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activities',
            $firstResponse->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $handler->handle();
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
    }
}
