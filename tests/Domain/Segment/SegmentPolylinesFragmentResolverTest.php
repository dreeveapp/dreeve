<?php

namespace App\Tests\Domain\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentPolylinesFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segment/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segment/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'segment.10.polylines',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheSegmentsItRenders(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segment/segment-10/polylines');

        // Without this tag a re-imported segment would keep serving its old route forever.
        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, segments',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/page/segment/segment-10/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentWithoutAMap(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/segment-1/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/segment-999/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedSegmentId(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segment/10/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
