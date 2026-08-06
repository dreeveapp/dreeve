<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\HeatmapApiRequestHandler;
use App\Infrastructure\Cache\RenderCache;
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

        $firstResponse = (string) $this->heatmapApiRequestHandler->routes()->getContent();
        $this->assertStringContainsString('Night Ride', $firstResponse);

        // Rename every route behind the repository's back, so a fresh render would look different.
        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = "This name never made it into the render cache"'
        );

        $this->assertEquals(
            $firstResponse,
            (string) $this->heatmapApiRequestHandler->routes()->getContent()
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
