<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiFragmentRequestHandler;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class ApiFragmentRequestHandlerTest extends ContainerTestCase
{
    use ProvideTestData;

    private ApiFragmentRequestHandler $apiFragmentRequestHandler;

    public function testHandleForRegisteredFragment(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiFragmentRequestHandler->handle('page', 'photos');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'text/html; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
        // The fragment declares a cache context, so it must never be shared by a reverse proxy.
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
        // The fragment does not declare a lifetime, so there is none to report.
        $this->assertFalse($response->headers->has('X-Cache-TTL'));

        $secondResponse = $this->apiFragmentRequestHandler->handle('page', 'photos');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Key'),
            $secondResponse->headers->get('X-Cache-Key'),
        );
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAFragmentResolver(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiFragmentRequestHandler->handle('page', 'rewind/2023/compare/2022');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringContainsString('rewind.2023.compare.2022', (string) $response->headers->get('X-Cache-Key'));
        // Only the two years that are actually rendered can invalidate this fragment.
        $this->assertEquals(
            'settings.appearance, settings.general, activities.2023, activity.images.2023, gear, activities.2022, activity.images.2022',
            $response->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $this->apiFragmentRequestHandler->handle('page', 'rewind/2023/compare/2022');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAnActivityDetail(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiFragmentRequestHandler->handle('page', 'activity/activity-9756441741');

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

        $secondResponse = $this->apiFragmentRequestHandler->handle('page', 'activity/activity-9756441741');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForADataFragment(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiFragmentRequestHandler->handle('data', 'activity/activity-9756441741/metrics');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'application/json',
            $response->headers->get('Content-Type'),
        );
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringEndsWith('activity.9756441741.metrics', (string) $response->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activities.9756441741',
            $response->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $this->apiFragmentRequestHandler->handle('data', 'activity/activity-9756441741/metrics');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForAFragmentThatOptsOutOfTheRenderCache(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiFragmentRequestHandler->handle('partial', 'gear/maintenance-due');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'text/html; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
        $this->assertEquals('UNCACHEABLE', $response->headers->get('X-Cache'));
        $this->assertFalse($response->headers->has('X-Cache-Key'));
        $this->assertEquals('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testHandleWhenTheTypeDoesNotMatchTheFragment(): void
    {
        $this->provideFullTestSet();

        // Each fragment is only served under the segment matching its own type.
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'photos')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'activity/activity-9756441741/metrics')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'gear/maintenance-due')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('partial', 'photos')->getStatusCode()
        );
    }

    public function testHandleForAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'activity/activity-1')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'activity/activity-1/coordinates')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'activity/activity-1/metrics')->getStatusCode()
        );
    }

    public function testHandleWhenFragmentIsNotRegistered(): void
    {
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'unknown')->getStatusCode()
        );
    }

    public function testHandleWhenAFragmentResolverCannotResolveThePath(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'rewind/1999')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'activity/9756441741/coordinates')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'activity/activity-9830227112/coordinates')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'activity/activity-9830227112/metrics')->getStatusCode()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->apiFragmentRequestHandler = new ApiFragmentRequestHandler(
            $this->getContainer()->get(FragmentRegistry::class),
            $this->getContainer()->get(FragmentRenderer::class),
        );
    }
}
