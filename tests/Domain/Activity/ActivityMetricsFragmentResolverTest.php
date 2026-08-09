<?php

namespace App\Tests\Domain\Activity;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideBuiltTestSet;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityMetricsFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideBuiltTestSet;

    public function testRender(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741/metrics');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741/metrics');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity.9756441741.metrics',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities.9756441741',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityWithoutACombinedStream(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/activity-1/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/9756441741/metrics');

        $this->assertResponseStatusCodeSame(404);
    }
}
