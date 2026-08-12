<?php

namespace App\Tests\Domain\Dashboard;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class DashboardFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotServedAsAPartialFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/partial/dashboard');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheDashboardItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/dashboard');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, dashboard',
        );
    }
}
