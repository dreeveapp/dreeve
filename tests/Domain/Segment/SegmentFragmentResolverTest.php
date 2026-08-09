<?php

namespace App\Tests\Domain\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithAMap(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/fragment/page/segment/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'segment.1',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheSegmentAndTheActivitiesItLists(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/segment-1');

        // Scoped to this segment, so importing an activity that rode another segment leaves it alone.
        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, segments.1, gear, activities.9542782314',
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/segment/segment-1');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotSwallowTheDataTableFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/data-table');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/segment-999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedSegmentId(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/segment/1');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
