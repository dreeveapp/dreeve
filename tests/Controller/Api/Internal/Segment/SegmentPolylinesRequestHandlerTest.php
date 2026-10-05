<?php

namespace App\Tests\Controller\Api\Internal\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;

class SegmentPolylinesRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/internal/segments/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringEndsWith(
            'segments.10.polylines',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        // Without this tag a re-imported segment would keep serving its old route forever.
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, segments',
        );
        $this->assertSame('[[[-11.64587,166.94827],[-11.64727,166.94921]]]', $this->client->getResponse()->getContent());
    }

    public function testItDoesNotResolveASegmentWithoutAMap(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/segments/segment-1/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/segments/segment-999/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
