<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityBestEffortsFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9542782314/best-efforts');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItEntersTheRenderCache(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9542782314/best-efforts');

        $this->assertResponseHeaderSame('X-Dreeve-Cache', 'MISS');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activities.9542782314, activities');
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9542782314/best-efforts');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-1/best-efforts');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/9542782314/best-efforts');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
