<?php

namespace App\Tests\Domain\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideBuiltTestSet;
use Spatie\Snapshots\MatchesSnapshots;

class ActivitySegmentsFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideBuiltTestSet;

    public function testRender(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9542782314/segments');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForActivityWithoutAnySegments(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9756441709/segments');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty(trim((string) $this->client->getResponse()->getContent()));
    }

    public function testItStaysOutOfTheRenderCache(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9542782314/segments');

        $this->assertResponseHeaderSame('X-Cache', 'UNCACHEABLE');
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9542782314/segments');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-1/segments');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/partial/activity/9542782314/segments');

        $this->assertResponseStatusCodeSame(404);
    }
}
