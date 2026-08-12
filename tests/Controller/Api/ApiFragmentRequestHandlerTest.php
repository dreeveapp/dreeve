<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiFragmentRequestHandler;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

/**
 * What each fragment renders lives next to that fragment. This is about the handler itself:
 * resolving a path through the registry, refusing the wrong type and consulting the render cache.
 */
class ApiFragmentRequestHandlerTest extends ContainerTestCase
{
    use ProvideTestData;

    private ApiFragmentRequestHandler $apiFragmentRequestHandler;

    public function testItServesEveryTypeUnderItsOwnSegment(): void
    {
        $this->provideFullTestSet();

        $page = $this->apiFragmentRequestHandler->handle('page', 'photos');
        $this->assertEquals(200, $page->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $page->headers->get('Content-Type'));

        $partial = $this->apiFragmentRequestHandler->handle('partial', 'gear/maintenance-due');
        $this->assertEquals(200, $partial->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $partial->headers->get('Content-Type'));

        $data = $this->apiFragmentRequestHandler->handle('data', 'heatmap/routes');
        $this->assertEquals(200, $data->getStatusCode());
        $this->assertEquals('application/json', $data->headers->get('Content-Type'));
    }

    public function testItServesTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();

        $firstResponse = $this->apiFragmentRequestHandler->handle('data', 'heatmap/routes');
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Dreeve-Cache'));

        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = "This name never made it into the render cache"'
        );

        $secondResponse = $this->apiFragmentRequestHandler->handle('data', 'heatmap/routes');
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Dreeve-Cache'));
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEquals(
            $firstResponse->headers->get('X-Dreeve-Cache-Key'),
            $secondResponse->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItDoesNotServeAFragmentUnderTheWrongType(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('data', 'photos')->getStatusCode()
        );
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'heatmap/routes')->getStatusCode()
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
