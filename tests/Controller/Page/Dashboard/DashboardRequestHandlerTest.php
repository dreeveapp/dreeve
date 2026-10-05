<?php

namespace App\Tests\Controller\Page\Dashboard;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class DashboardRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, dashboard',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsServedOnTheRoot(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('href="/dashboard" aria-selected="true"', (string) $this->client->getResponse()->getContent());
    }
}
