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
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItOnlyRendersTheEditLinkForAuthenticatedVisitors(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/dashboard');
        $this->assertStringNotContainsString(
            'admin/settings/dashboard',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/dashboard');
        $this->assertStringContainsString(
            'admin/settings/dashboard?redirectTo=%2Fdashboard',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/dashboard');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/dashboard');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheDashboardItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/dashboard');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, dashboard',
        );
    }

    public function testItMarksTheDashboardSectionAsActive(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/dashboard');

        $this->assertStringContainsString('href="/dashboard" aria-selected="true"', (string) $this->client->getResponse()->getContent());
    }

    public function testItIsServedOnTheRoot(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('href="/dashboard" aria-selected="true"', (string) $this->client->getResponse()->getContent());
    }
}
