<?php

namespace App\Tests\Domain\Activity;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityMetricsFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741/metrics');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741/metrics');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity.9756441741.metrics',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.9756441741',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityWithoutACombinedStream(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-1/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/9756441741/metrics');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
