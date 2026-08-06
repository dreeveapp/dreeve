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
        $this->assertFalse($response->headers->has('X-Cache-Hit'));
        $this->assertStringContainsString('photos.trust=', (string) $response->headers->get('X-Cache-Miss'));

        $secondResponse = $this->apiPageRequestHandler->handle('photos');
        $this->assertFalse($secondResponse->headers->has('X-Cache-Miss'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Miss'),
            $secondResponse->headers->get('X-Cache-Hit'),
        );
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleWhenPageIsNotRegistered(): void
    {
        $this->assertEquals(
            404,
            $this->apiPageRequestHandler->handle('unknown')->getStatusCode()
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
