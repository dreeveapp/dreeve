<?php

namespace App\Tests\Domain\Activity;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityDataTableFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity.data-table',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheActivitiesItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/data-table');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/activity/data-table');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
