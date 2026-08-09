<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiPageRequestHandler;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Http\Page\PageRegistry;
use App\Infrastructure\Http\Page\PageRenderer;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class ApiPageRequestHandlerTest extends ContainerTestCase
{
    use ProvideTestData;

    private ApiPageRequestHandler $apiPageRequestHandler;

    public function testHandleForRegisteredPage(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiPageRequestHandler->handle('photos');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'text/html; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
        // The page declares a cache context, so it must never be shared by a reverse proxy.
        $this->assertEquals(
            'no-store, private',
            $response->headers->get('Cache-Control'),
        );
        $this->assertStringContainsString('data-image', (string) $response->getContent());
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringContainsString('photos.trust=', (string) $response->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activity.images',
            $response->headers->get('X-Cache-Tags'),
        );
        // The page does not declare a lifetime, so there is none to report.
        $this->assertFalse($response->headers->has('X-Cache-TTL'));

        $secondResponse = $this->apiPageRequestHandler->handle('photos');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Key'),
            $secondResponse->headers->get('X-Cache-Key'),
        );
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAPageResolver(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiPageRequestHandler->handle('rewind/2023/compare/2022');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringContainsString('rewind.2023.compare.2022', (string) $response->headers->get('X-Cache-Key'));
        // Only the two years that are actually rendered can invalidate this page.
        $this->assertEquals(
            'settings.appearance, settings.general, activities.2023, activity.images.2023, gear, activities.2022, activity.images.2022',
            $response->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $this->apiPageRequestHandler->handle('rewind/2023/compare/2022');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAnActivityDetail(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiPageRequestHandler->handle('activity/activity-9756441741');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'no-store, private',
            $response->headers->get('Cache-Control'),
        );
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringEndsWith('activity.9756441741.auth=anon', (string) $response->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activities.9756441741, gear',
            $response->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $this->apiPageRequestHandler->handle('activity/activity-9756441741');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            404,
            $this->apiPageRequestHandler->handle('activity/activity-1')->getStatusCode()
        );
    }

    public function testHandleWhenPageIsNotRegistered(): void
    {
        $this->assertEquals(
            404,
            $this->apiPageRequestHandler->handle('unknown')->getStatusCode()
        );
    }

    public function testHandleWhenAPageResolverCannotResolveThePath(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            404,
            $this->apiPageRequestHandler->handle('rewind/1999')->getStatusCode()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->apiPageRequestHandler = new ApiPageRequestHandler(
            $this->getContainer()->get(PageRegistry::class),
            $this->getContainer()->get(PageRenderer::class),
        );
    }
}
