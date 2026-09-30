<?php

namespace App\Tests\Controller\Api\Internal;

use App\Application\NotFoundFragment;
use App\Controller\Api\Internal\ApiFragmentRequestHandler;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class ApiFragmentRequestHandlerTest extends ContainerTestCase
{
    use ProvideTestData;

    private ApiFragmentRequestHandler $apiFragmentRequestHandler;

    public function testHandleWhenFragmentIsNotRegistered(): void
    {
        $this->assertEquals(
            404,
            $this->apiFragmentRequestHandler->handle('page', 'unknown')->getStatusCode()
        );
    }

    public function testItRendersTheNotFoundPageForAnUnknownPage(): void
    {
        $response = $this->apiFragmentRequestHandler->handle('page', 'unknown');

        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('404', (string) $response->getContent());
        $this->assertStringContainsString('wandered off the map', (string) $response->getContent());
    }

    public function testItServesTheNotFoundPageTheRouterFallsBackTo(): void
    {
        $response = $this->apiFragmentRequestHandler->handle('page', 'not-found');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('wandered off the map', (string) $response->getContent());
    }

    public function testItLeavesAnUnknownPartialAndDataFragmentEmpty(): void
    {
        foreach (['partial', 'data'] as $type) {
            $response = $this->apiFragmentRequestHandler->handle($type, 'unknown');

            $this->assertEquals(404, $response->getStatusCode());
            $this->assertEquals('', $response->getContent());
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->apiFragmentRequestHandler = new ApiFragmentRequestHandler(
            $this->getContainer()->get(FragmentRegistry::class),
            $this->getContainer()->get(FragmentRenderer::class),
            $this->getContainer()->get(NotFoundFragment::class),
        );
    }
}
