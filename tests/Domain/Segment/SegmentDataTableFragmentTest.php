<?php

namespace App\Tests\Domain\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentDataTableFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'segment.data-table',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/data-table');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheSegmentsItRenders(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/data-table');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, segments',
        );
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
