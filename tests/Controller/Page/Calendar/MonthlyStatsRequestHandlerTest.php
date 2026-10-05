<?php

namespace App\Tests\Controller\Page\Calendar;

use App\Controller\Page\Calendar\MonthlyStatsRequestHandler;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class MonthlyStatsRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'monthly-stats',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, '.RootCacheTag::ACTIVITIES->toTagString(),
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithoutActivities(): void
    {
        $this->assertMatchesHtmlSnapshot(
            (string) $this->getContainer()->get(MonthlyStatsRequestHandler::class)->handle()->getContent()
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
