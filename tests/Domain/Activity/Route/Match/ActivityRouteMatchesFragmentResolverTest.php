<?php

namespace App\Tests\Domain\Activity\Route\Match;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateActivityRouteSignatures;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityRouteMatchesFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->calculateRouteSignatures();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9830227167/route-matches');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItEntersTheRenderCache(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-9830227167/route-matches');

        $this->assertResponseHeaderSame('X-Dreeve-Cache', 'MISS');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activities.9830227167, activities');
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9830227167/route-matches');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/activity-1/route-matches');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/partial/activity/9830227167/route-matches');

        $this->assertResponseStatusCodeSame(404);
    }

    private function calculateRouteSignatures(): void
    {
        $this->getContainer()->get(CalculateActivityRouteSignatures::class)->process(new SpyOutput());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
