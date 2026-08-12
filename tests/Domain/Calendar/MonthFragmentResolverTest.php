<?php

namespace App\Tests\Domain\Calendar;

use App\Domain\Calendar\MonthFragmentResolver;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MonthFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2023-06');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderJanuary(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2023-01');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2023-06');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'month.2023-06',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/month/2023-06');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheMonthsItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2023-01');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.2022-12, activities.2023-01, activities.2023-02',
        );
    }

    public function testItResolvesEveryMonthBetweenTheFirstActivityAndToday(): void
    {
        // The clock is paused on 2023-10-17, the test set starts in July 2020.
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2020-07');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/fragment/page/month/2023-10');
        $this->assertResponseIsSuccessful();
    }

    public function testItDoesNotResolveMonthsOutsideThatRange(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/month/2020-06');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/fragment/page/month/2023-11');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveMalformedPaths(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $monthFragmentResolver = $this->getContainer()->get(MonthFragmentResolver::class);

        $this->assertNull($monthFragmentResolver->resolve('month/2023-13'));
        $this->assertNull($monthFragmentResolver->resolve('month/2023-6'));
        $this->assertNull($monthFragmentResolver->resolve('month/not-a-month'));
        $this->assertNull($monthFragmentResolver->resolve('month'));
        $this->assertNull($monthFragmentResolver->resolve('monthly-stats'));
    }

    /**
     * AppHasActivitiesGate redirects every request while there are no activities, so an empty
     * database can only be observed through the resolver.
     */
    public function testItDoesNotResolveWhenThereAreNoActivities(): void
    {
        $this->assertNull($this->getContainer()->get(MonthFragmentResolver::class)->resolve('month/2023-06'));
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
