<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\HeatmapApiRequestHandler;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapApiRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private HeatmapApiRequestHandler $heatmapApiRequestHandler;

    public function testRoutes(): void
    {
        $this->provideFullTestSet();

        $response = $this->heatmapApiRequestHandler->routes();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertMatchesJsonSnapshot((string) $response->getContent());
    }

    public function testRoutesItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();

        $response = $this->heatmapApiRequestHandler->routes();
        $this->assertStringContainsString('Night Ride', (string) $response->getContent());
        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertStringContainsString('heatmap.routes', (string) $response->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activity.route',
            $response->headers->get('X-Cache-Tags'),
        );

        // Rename every route behind the repository's back, so a fresh render would look different.
        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = "This name never made it into the render cache"'
        );

        $secondResponse = $this->heatmapApiRequestHandler->routes();
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Key'),
            $secondResponse->headers->get('X-Cache-Key'),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->heatmapApiRequestHandler = $this->getContainer()->get(HeatmapApiRequestHandler::class);
    }
}
