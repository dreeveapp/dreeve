<?php

namespace App\Tests\Domain\Calendar;

use App\Domain\Calendar\MonthlyStatsFragment;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MonthlyStatsFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/monthly-stats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithoutActivities(): void
    {
        $this->assertMatchesHtmlSnapshot(
            (string) $this->getContainer()->get(MonthlyStatsFragment::class)->render()
        );
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/monthly-stats');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'monthly-stats',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/monthly-stats');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetCacheTags(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/monthly-stats');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, '.RootCacheTag::ACTIVITIES->toTagString(),
        );
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
