<?php

namespace App\Tests\Domain\Activity\Route;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapRoutesFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/heatmap/routes');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringContainsString('Night Ride', (string) $this->client->getResponse()->getContent());
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/heatmap/routes');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'heatmap.routes',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheRoutesItRenders(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/heatmap/routes');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activity.route',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/heatmap/routes');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
