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
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segments/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segments/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'segments.10.polylines',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheSegmentsItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segments/segment-10/polylines');

        // Without this tag a re-imported segment would keep serving its old route forever.
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, segments',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/page/segments/segment-10/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentWithoutAMap(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/segments/segment-1/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/segments/segment-999/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedSegmentId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/data/segments/10/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
